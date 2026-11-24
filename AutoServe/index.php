<?php
session_start();
require_once __DIR__ . '/config/database.php';

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function go($page = 'dashboard') { header("Location: index.php?page=" . urlencode($page)); exit; }
function logged() { return isset($_SESSION['user']); }
function role() { return $_SESSION['user']['role'] ?? ''; }
function requireLogin() { if (!logged()) go('login'); }
function flash($message, $type='success') { $_SESSION['flash'] = [$message, $type]; }
function activeCount(PDO $pdo, int $slotId): int {
    $q = $pdo->prepare("SELECT COUNT(*) FROM `ServiceBooking` WHERE slot_id=? AND status IN ('PENDING','CONFIRMED','IN_PROGRESS')");
    $q->execute([$slotId]); return (int)$q->fetchColumn();
}
$page = $_GET['page'] ?? (logged() ? 'dashboard' : 'login');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'register') {
            $name = trim($_POST['full_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new Exception('Enter a name, valid email, and password with at least 8 characters.');
            }
            $q = $pdo->prepare("INSERT INTO `Owner` (full_name,email,phone,password_hash) VALUES (?,?,?,?)");
            $q->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
            flash('Account created. You can now log in.');
            go('login');
        }
        if ($action === 'login') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $q = $pdo->prepare("SELECT owner_id AS id, full_name, email, password_hash, 'OWNER' AS role FROM `Owner` WHERE email=?");
            $q->execute([$email]); $user = $q->fetch();
            if (!$user) {
                $q = $pdo->prepare("SELECT mechanic_id AS id, full_name, email, password_hash, role FROM `Mechanic` WHERE email=?");
                $q->execute([$email]); $user = $q->fetch();
            }
            if (!$user || !password_verify($password, $user['password_hash'])) throw new Exception('Incorrect email or password.');
            unset($user['password_hash']);
            session_regenerate_id(true); $_SESSION['user'] = $user;
            go('dashboard');
        }
        if ($action === 'logout') { $_SESSION = []; session_destroy(); header('Location: index.php?page=login'); exit; }
        requireLogin();
        $u = $_SESSION['user'];

        if ($action === 'add_vehicle' || $action === 'edit_vehicle') {
            if (role() !== 'OWNER') throw new Exception('Only owners can manage vehicles.');
            $plate = strtoupper(trim($_POST['plate_no'] ?? ''));
            $make = trim($_POST['make'] ?? ''); $model = trim($_POST['model'] ?? '');
            $year = (int)($_POST['year'] ?? 0); $color = trim($_POST['color'] ?? '');
            $mileage = (int)($_POST['mileage_km'] ?? 0);
            if (!$plate || !$make || !$model || $year < 1980 || $year > 2100 || $mileage < 0) throw new Exception('Please complete the vehicle fields with valid values.');
            if ($action === 'add_vehicle') {
                $q=$pdo->prepare("INSERT INTO `Vehicle` (owner_id,plate_no,make,model,`year`,color,mileage_km) VALUES (?,?,?,?,?,?,?)");
                $q->execute([$u['id'],$plate,$make,$model,$year,$color,$mileage]);
            } else {
                $q=$pdo->prepare("UPDATE `Vehicle` SET plate_no=?,make=?,model=?,`year`=?,color=?,mileage_km=? WHERE vehicle_id=? AND owner_id=?");
                $q->execute([$plate,$make,$model,$year,$color,$mileage,(int)$_POST['vehicle_id'],$u['id']]);
            }
            flash('Vehicle saved.'); go('vehicles');
        }
        if ($action === 'delete_vehicle') {
            if (role() !== 'OWNER') throw new Exception('Not allowed.');
            $q=$pdo->prepare("DELETE FROM `Vehicle` WHERE vehicle_id=? AND owner_id=?");
            $q->execute([(int)$_POST['vehicle_id'],$u['id']]);
            flash('Vehicle deleted.'); go('vehicles');
        }
        if ($action === 'add_mechanic') {
            if (role() !== 'MANAGER') throw new Exception('Only the manager can add mechanics.');
            $name = trim($_POST['full_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new Exception('Enter a name, valid email, and password with at least 8 characters.');
            }
            $q = $pdo->prepare("SELECT 1 FROM `Owner` WHERE email=?");
            $q->execute([$email]);
            if ($q->fetch()) throw new Exception('That email is already used by an owner account.');
            $q = $pdo->prepare("INSERT INTO `Mechanic` (full_name,email,password_hash,role,phone) VALUES (?,?,?,'MECHANIC',?)");
            $q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$phone]);
            flash('Mechanic added.'); go('mechanics');
        }
        if ($action === 'save_slot') {
            if (role() !== 'MANAGER') throw new Exception('Only the manager can manage service slots.');
            $id=(int)($_POST['slot_id'] ?? 0); $mechanic=(int)$_POST['mechanic_id'];
            $date=$_POST['slot_date']; $start=$_POST['start_time']; $end=$_POST['end_time']; $capacity=(int)$_POST['capacity'];
            if ($date < date('Y-m-d') || $end <= $start || $capacity < 1 || $capacity > 3) throw new Exception('Check the date, time range and capacity (1 to 3).');
            if ($id) {
                $q=$pdo->prepare("UPDATE `ServiceSlot` SET mechanic_id=?,slot_date=?,start_time=?,end_time=?,capacity=? WHERE slot_id=?");
                $q->execute([$mechanic,$date,$start,$end,$capacity,$id]);
            } else {
                $q=$pdo->prepare("INSERT INTO `ServiceSlot` (mechanic_id,slot_date,start_time,end_time,capacity) VALUES (?,?,?,?,?)");
                $q->execute([$mechanic,$date,$start,$end,$capacity]);
            }
            flash('Service slot saved.'); go('slots');
        }
        if ($action === 'close_slot') {
            if (role() !== 'MANAGER') throw new Exception('Not allowed.');
            $q=$pdo->prepare("UPDATE `ServiceSlot` SET status='CLOSED' WHERE slot_id=?");
            $q->execute([(int)$_POST['slot_id']]); flash('Slot closed.'); go('slots');
        }
        if ($action === 'book') {
            if (role() !== 'OWNER') throw new Exception('Only owners can book a service.');
            $vehicle=(int)$_POST['vehicle_id']; $slot=(int)$_POST['slot_id']; $service=(int)$_POST['service_type_id'];
            $q=$pdo->prepare("SELECT vehicle_id FROM `Vehicle` WHERE vehicle_id=? AND owner_id=?");
            $q->execute([$vehicle,$u['id']]); if (!$q->fetch()) throw new Exception('Choose one of your own vehicles.');
            $q=$pdo->prepare("SELECT * FROM `ServiceSlot` WHERE slot_id=? AND status='OPEN' AND slot_date>=CURDATE()");
            $q->execute([$slot]); $s=$q->fetch(); if (!$s) throw new Exception('This slot is not available.');
            if (activeCount($pdo,$slot) >= (int)$s['capacity']) throw new Exception('Slot is fully booked.');
            $q=$pdo->prepare("SELECT COUNT(*) FROM `ServiceBooking` WHERE vehicle_id=? AND slot_id=? AND status IN ('PENDING','CONFIRMED','IN_PROGRESS')");
            $q->execute([$vehicle,$slot]); if ((int)$q->fetchColumn()>0) throw new Exception('This vehicle already has an active booking in this slot.');
            $q=$pdo->prepare("INSERT INTO `ServiceBooking` (owner_id,vehicle_id,slot_id,service_type_id,notes) VALUES (?,?,?,?,?)");
            $q->execute([$u['id'],$vehicle,$slot,$service,trim($_POST['notes'] ?? '')]);
            flash('Booking created with Pending status.'); go('bookings');
        }
        if ($action === 'cancel_booking') {
            $id=(int)$_POST['booking_id'];
            if (role() !== 'OWNER') throw new Exception('Only owners can cancel bookings here.');
            $q=$pdo->prepare("UPDATE `ServiceBooking` SET status='CANCELLED' WHERE booking_id=? AND owner_id=? AND status IN ('PENDING','CONFIRMED')");
            $q->execute([$id,$u['id']]); flash($q->rowCount() ? 'Booking cancelled.' : 'Booking could not be cancelled.'); go('bookings');
        }
        if ($action === 'update_status') {
            if (!in_array(role(),['MECHANIC','MANAGER'],true)) throw new Exception('Not allowed.');
            $id=(int)$_POST['booking_id']; $new=$_POST['status'];
            $q=$pdo->prepare("SELECT b.status,s.mechanic_id FROM `ServiceBooking` b JOIN `ServiceSlot` s ON s.slot_id=b.slot_id WHERE b.booking_id=?");
            $q->execute([$id]); $b=$q->fetch(); if (!$b) throw new Exception('Booking not found.');
            if (role()==='MECHANIC' && (int)$b['mechanic_id'] !== (int)$u['id']) throw new Exception('You can only update bookings assigned to you.');
            $allowed=['PENDING'=>['CONFIRMED'],'CONFIRMED'=>['IN_PROGRESS'],'IN_PROGRESS'=>['COMPLETED']];
            if (!in_array($new,$allowed[$b['status']] ?? [],true)) throw new Exception('Invalid status change for Phase 2 workflow.');
            $q=$pdo->prepare("UPDATE `ServiceBooking` SET status=?, completed_at=IF(?='COMPLETED',NOW(),completed_at) WHERE booking_id=?");
            $q->execute([$new,$new,$id]); flash('Booking status updated.'); go('jobs');
        }
    } catch (PDOException $e) {
        if ((string)$e->getCode()==='23000') $error='That email, plate number or slot already exists. Please check your entry.';
        else $error='Database operation failed. Check the entered information and database setup.';
    } catch (Exception $e) { $error=$e->getMessage(); }
}

if ($page !== 'login' && $page !== 'register') requireLogin();
$u = $_SESSION['user'] ?? null;
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

function rows(PDO $pdo, string $sql, array $params=[]): array { $q=$pdo->prepare($sql); $q->execute($params); return $q->fetchAll(); }
function one(PDO $pdo, string $sql, array $params=[]): ?array { $q=$pdo->prepare($sql); $q->execute($params); $r=$q->fetch(); return $r ?: null; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>AutoServe</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6fa;color:#1f2937}.navbar{background:#18345b}.navbar-brand,.nav-link{color:#fff!important}.sidebar{background:#fff;border-radius:14px;padding:18px;min-height:70vh}.sidebar a{display:block;padding:10px 12px;color:#263b5b;text-decoration:none;border-radius:8px}.sidebar a:hover{background:#e9eff8}.card{border:0;border-radius:14px;box-shadow:0 4px 18px #10233a0d}.btn-primary{background:#244f88;border-color:#244f88}.page-title{font-weight:700;color:#18345b}.table thead{background:#e8eef8}
</style></head><body>
<nav class="navbar navbar-expand-lg"><div class="container-fluid px-4"><a class="navbar-brand fw-bold" href="index.php">AutoServe</a>
<?php if($u): ?><span class="text-white me-3"><?=h($u['full_name'])?> · <?=h($u['role'])?></span><form method="post"><input type="hidden" name="action" value="logout"><button class="btn btn-outline-light btn-sm">Logout</button></form><?php endif; ?></div></nav>
<div class="container-fluid py-4"><div class="row g-4">
<?php if($u): ?><aside class="col-lg-2"><div class="sidebar"><div class="fw-bold mb-2">Navigation</div>
<a href="index.php?page=dashboard">Dashboard</a>
<?php if(role()==='OWNER'): ?><a href="index.php?page=vehicles">My Vehicles</a><a href="index.php?page=browse">Browse Service Slots</a><a href="index.php?page=book">Book Service</a><a href="index.php?page=bookings">My Bookings</a><?php endif; ?>
<?php if(role()==='MECHANIC'): ?><a href="index.php?page=jobs">My Job List</a><?php endif; ?>
<?php if(role()==='MANAGER'): ?><a href="index.php?page=slots">Manage Slots</a><a href="index.php?page=mechanics">Manage Mechanics</a><a href="index.php?page=jobs">All Bookings / Jobs</a><?php endif; ?>
</div></aside><?php endif; ?>
<main class="<?= $u?'col-lg-10':'col-lg-6 mx-auto' ?>">
<?php if($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
<?php if($flash): ?><div class="alert alert-<?=h($flash[1])?>"><?=h($flash[0])?></div><?php endif; ?>

<?php if($page==='login'): ?>
<div class="card p-4 mt-5"><h2 class="page-title">Sign in to AutoServe</h2><p class="text-muted">Vehicle Service Scheduling System</p>
<form method="post"><input type="hidden" name="action" value="login"><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div><button class="btn btn-primary w-100">Login</button></form><p class="mt-3 mb-0">New vehicle owner? <a href="index.php?page=register">Create an account</a></p></div>

<?php elseif($page==='register'): ?>
<div class="card p-4 mt-5"><h2 class="page-title">Owner Registration</h2><form method="post"><input type="hidden" name="action" value="register">
<div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="full_name" required></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone"></div><div class="mb-3"><label class="form-label">Password (minimum 8 characters)</label><input class="form-control" type="password" name="password" minlength="8" required></div><button class="btn btn-primary">Register</button> <a class="btn btn-light" href="index.php?page=login">Back</a></form></div>

<?php elseif($page==='dashboard'): ?>
<h2 class="page-title mb-4">Dashboard</h2><div class="row g-3">
<?php
if(role()==='OWNER'){
 $v=one($pdo,"SELECT COUNT(*) n FROM `Vehicle` WHERE owner_id=?",[$u['id']])['n'];
 $b=one($pdo,"SELECT COUNT(*) n FROM `ServiceBooking` WHERE owner_id=?",[$u['id']])['n'];
 $up=one($pdo,"SELECT COUNT(*) n FROM `ServiceBooking` WHERE owner_id=? AND status IN ('PENDING','CONFIRMED','IN_PROGRESS')",[$u['id']])['n'];
} else {
 $where=role()==='MECHANIC'?"JOIN `ServiceSlot` s ON s.slot_id=b.slot_id WHERE s.mechanic_id=?":"";
 $params=role()==='MECHANIC'?[$u['id']]:[];
 $b=one($pdo,"SELECT COUNT(*) n FROM `ServiceBooking` b $where",$params)['n'];
 $up=one($pdo,"SELECT COUNT(*) n FROM `ServiceBooking` b $where ".($where?"AND":"WHERE")." b.status IN ('PENDING','CONFIRMED','IN_PROGRESS')",$params)['n'];
 $v=one($pdo,"SELECT COUNT(*) n FROM `Vehicle`")['n'];
}
foreach([['Vehicles',$v],['Bookings',$b],['Active Bookings',$up]] as $stat): ?>
<div class="col-md-4"><div class="card p-4"><div class="text-muted"><?=h($stat[0])?></div><div class="display-5 fw-bold"><?=h($stat[1])?></div></div></div><?php endforeach; ?></div>
<div class="card p-4 mt-4"><h5>Welcome, <?=h($u['full_name'])?>!</h5><p class="mb-0">Use the navigation menu to access your AutoServe features.</p></div>

<?php elseif($page==='vehicles' && role()==='OWNER'): ?>
<h2 class="page-title">My Vehicles</h2>
<?php $edit=isset($_GET['edit'])?one($pdo,"SELECT * FROM `Vehicle` WHERE vehicle_id=? AND owner_id=?",[(int)$_GET['edit'],$u['id']]):null; ?>
<div class="card p-4 mb-4"><h5><?= $edit?'Edit Vehicle':'Add Vehicle' ?></h5><form method="post" class="row g-3"><input type="hidden" name="action" value="<?=$edit?'edit_vehicle':'add_vehicle'?>"><?php if($edit): ?><input type="hidden" name="vehicle_id" value="<?=$edit['vehicle_id']?>"><?php endif; ?>
<?php foreach(['plate_no'=>'Plate number','make'=>'Make','model'=>'Model','year'=>'Year','color'=>'Color','mileage_km'=>'Mileage (km)'] as $field=>$label): ?><div class="col-md-4"><label class="form-label"><?=h($label)?></label><input class="form-control" name="<?=h($field)?>" value="<?=h($edit[$field]??'')?>" <?=in_array($field,['plate_no','make','model','year'])?'required':''?>></div><?php endforeach; ?>
<div class="col-12"><button class="btn btn-primary">Save Vehicle</button><?php if($edit): ?> <a class="btn btn-light" href="index.php?page=vehicles">Cancel</a><?php endif; ?></div></form></div>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Plate</th><th>Vehicle</th><th>Year</th><th>Color</th><th>Mileage</th><th>Actions</th></tr></thead><tbody>
<?php foreach(rows($pdo,"SELECT * FROM `Vehicle` WHERE owner_id=? ORDER BY vehicle_id DESC",[$u['id']]) as $r): ?><tr><td><?=h($r['plate_no'])?></td><td><?=h($r['make'].' '.$r['model'])?></td><td><?=h($r['year'])?></td><td><?=h($r['color'])?></td><td><?=h($r['mileage_km'])?></td><td><a class="btn btn-sm btn-outline-primary" href="index.php?page=vehicles&edit=<?=$r['vehicle_id']?>">Edit</a> <form class="d-inline" method="post" onsubmit="return confirm('Delete this vehicle?')"><input type="hidden" name="action" value="delete_vehicle"><input type="hidden" name="vehicle_id" value="<?=$r['vehicle_id']?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<?php elseif($page==='slots' && role()==='MANAGER'): ?>
<h2 class="page-title">Manage Service Slots</h2><?php $mechanics=rows($pdo,"SELECT mechanic_id,full_name FROM `Mechanic` WHERE role='MECHANIC' ORDER BY full_name"); $edit=isset($_GET['edit'])?one($pdo,"SELECT * FROM `ServiceSlot` WHERE slot_id=? ",[(int)$_GET['edit']]):null; ?>
<div class="card p-4 mb-4"><h5><?= $edit?'Edit Slot':'Create Slot' ?></h5><form method="post" class="row g-3"><input type="hidden" name="action" value="save_slot"><?php if($edit): ?><input type="hidden" name="slot_id" value="<?=$edit['slot_id']?>"><?php endif; ?>
<div class="col-md-3"><label class="form-label">Mechanic</label><select class="form-select" name="mechanic_id" required><?php foreach($mechanics as $m): ?><option value="<?=$m['mechanic_id']?>" <?=($edit['mechanic_id']??'')==$m['mechanic_id']?'selected':''?>><?=h($m['full_name'])?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label">Date</label><input class="form-control" type="date" name="slot_date" min="<?=date('Y-m-d')?>" value="<?=h($edit['slot_date']??'')?>" required></div>
<div class="col-md-2"><label class="form-label">Start</label><input class="form-control" type="time" name="start_time" value="<?=h($edit['start_time']??'')?>" required></div>
<div class="col-md-2"><label class="form-label">End</label><input class="form-control" type="time" name="end_time" value="<?=h($edit['end_time']??'')?>" required></div>
<div class="col-md-1"><label class="form-label">Capacity</label><input class="form-control" type="number" name="capacity" min="1" max="3" value="<?=h($edit['capacity']??1)?>" required></div><div class="col-12"><button class="btn btn-primary">Save Slot</button></div></form></div>
<div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Time</th><th>Mechanic</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach(rows($pdo,"SELECT s.*,m.full_name mechanic FROM `ServiceSlot` s JOIN `Mechanic` m ON m.mechanic_id=s.mechanic_id ORDER BY slot_date,start_time") as $s): ?><tr><td><?=h($s['slot_date'])?></td><td><?=h(substr($s['start_time'],0,5).' - '.substr($s['end_time'],0,5))?></td><td><?=h($s['mechanic'])?></td><td><?=h($s['capacity'])?></td><td><?=h($s['status'])?></td><td><a class="btn btn-sm btn-outline-primary" href="index.php?page=slots&edit=<?=$s['slot_id']?>">Edit</a> <form class="d-inline" method="post"><input type="hidden" name="action" value="close_slot"><input type="hidden" name="slot_id" value="<?=$s['slot_id']?>"><button class="btn btn-sm btn-outline-danger">Close</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>

<?php elseif($page==='mechanics' && role()==='MANAGER'): ?>
<h2 class="page-title">Manage Mechanics</h2>
<div class="card p-4 mb-4"><h5>Add Mechanic</h5><form method="post" class="row g-3"><input type="hidden" name="action" value="add_mechanic">
<div class="col-md-3"><label class="form-label">Full name</label><input class="form-control" name="full_name" required></div>
<div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
<div class="col-md-2"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
<div class="col-md-3"><label class="form-label">Password (min 8 characters)</label><input class="form-control" type="password" name="password" minlength="8" required></div>
<div class="col-12"><button class="btn btn-primary">Add Mechanic</button></div></form></div>
<div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th></tr></thead><tbody>
<?php foreach(rows($pdo,"SELECT mechanic_id,full_name,email,phone FROM `Mechanic` WHERE role='MECHANIC' ORDER BY full_name") as $m): ?><tr><td><?=$m['mechanic_id']?></td><td><?=h($m['full_name'])?></td><td><?=h($m['email'])?></td><td><?=h($m['phone'])?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<?php elseif($page==='browse' && role()==='OWNER'): ?>
<h2 class="page-title">Browse Service Slots</h2><form class="card p-3 mb-3 row g-2" method="get"><input type="hidden" name="page" value="browse"><div class="col-md-4"><label class="form-label">Filter date</label><input class="form-control" type="date" name="date" value="<?=h($_GET['date']??'')?>"></div><div class="col-md-4"><label class="form-label">Mechanic</label><select class="form-select" name="mechanic"><option value="">All mechanics</option><?php foreach(rows($pdo,"SELECT mechanic_id,full_name FROM `Mechanic` WHERE role='MECHANIC'") as $m): ?><option value="<?=$m['mechanic_id']?>" <?=($_GET['mechanic']??'')==$m['mechanic_id']?'selected':''?>><?=h($m['full_name'])?></option><?php endforeach; ?></select></div><div class="col-md-2 align-self-end"><button class="btn btn-primary">Search</button></div></form>
<?php $sql="SELECT s.*,m.full_name mechanic FROM `ServiceSlot` s JOIN `Mechanic` m ON m.mechanic_id=s.mechanic_id WHERE s.status='OPEN' AND s.slot_date>=CURDATE()"; $p=[]; if(!empty($_GET['date'])){$sql.=" AND s.slot_date=?";$p[]=$_GET['date'];} if(!empty($_GET['mechanic'])){$sql.=" AND s.mechanic_id=?";$p[]=(int)$_GET['mechanic'];} $sql.=" ORDER BY s.slot_date,s.start_time"; ?>
<div class="card p-3"><table class="table"><thead><tr><th>Date</th><th>Time</th><th>Mechanic</th><th>Seats left</th></tr></thead><tbody><?php foreach(rows($pdo,$sql,$p) as $s): $left=max(0,(int)$s['capacity']-activeCount($pdo,(int)$s['slot_id'])); ?><tr><td><?=h($s['slot_date'])?></td><td><?=h(substr($s['start_time'],0,5).' - '.substr($s['end_time'],0,5))?></td><td><?=h($s['mechanic'])?></td><td><?=h($left)?> / <?=h($s['capacity'])?></td></tr><?php endforeach; ?></tbody></table></div>

<?php elseif($page==='book' && role()==='OWNER'): ?>
<h2 class="page-title">Book a Service</h2><div class="card p-4"><form method="post"><input type="hidden" name="action" value="book"><div class="mb-3"><label class="form-label">Vehicle</label><select class="form-select" name="vehicle_id" required><?php foreach(rows($pdo,"SELECT vehicle_id,plate_no,make,model FROM `Vehicle` WHERE owner_id=?",[$u['id']]) as $v): ?><option value="<?=$v['vehicle_id']?>"><?=h($v['plate_no'].' - '.$v['make'].' '.$v['model'])?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Service type</label><select class="form-select" name="service_type_id" required><?php foreach(rows($pdo,"SELECT * FROM `ServiceType` ORDER BY name") as $st): ?><option value="<?=$st['service_type_id']?>"><?=h($st['name'].' · ₱'.number_format($st['base_price'],2))?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Available slot</label><select class="form-select" name="slot_id" required><?php foreach(rows($pdo,"SELECT s.*,m.full_name mechanic FROM `ServiceSlot` s JOIN `Mechanic` m ON m.mechanic_id=s.mechanic_id WHERE s.status='OPEN' AND s.slot_date>=CURDATE() ORDER BY s.slot_date,s.start_time") as $s): $left=max(0,(int)$s['capacity']-activeCount($pdo,(int)$s['slot_id'])); if($left>0): ?><option value="<?=$s['slot_id']?>"><?=h($s['slot_date'].' '.substr($s['start_time'],0,5).' - '.$s['mechanic'].' ('.$left.' seats left)')?></option><?php endif; endforeach; ?></select></div><div class="mb-3"><label class="form-label">Notes for mechanic (optional)</label><textarea class="form-control" name="notes" rows="3"></textarea></div><button class="btn btn-primary">Submit Booking</button></form></div>

<?php elseif($page==='bookings' && role()==='OWNER'): ?>
<h2 class="page-title">My Bookings</h2><form class="card p-3 mb-3 row g-2" method="get"><input type="hidden" name="page" value="bookings"><div class="col-md-4"><input class="form-control" name="q" placeholder="Search plate number" value="<?=h($_GET['q']??'')?>"></div><div class="col-md-3"><select class="form-select" name="status"><option value="">All statuses</option><?php foreach(['PENDING','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED'] as $st): ?><option <?=($_GET['status']??'')===$st?'selected':''?>><?=$st?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary">Filter</button></div></form>
<?php $sql="SELECT b.*,v.plate_no,v.make,v.model,st.name service,s.slot_date,s.start_time,m.full_name mechanic FROM `ServiceBooking` b JOIN `Vehicle` v ON v.vehicle_id=b.vehicle_id JOIN `ServiceType` st ON st.service_type_id=b.service_type_id JOIN `ServiceSlot` s ON s.slot_id=b.slot_id JOIN `Mechanic` m ON m.mechanic_id=s.mechanic_id WHERE b.owner_id=?"; $p=[$u['id']]; if(!empty($_GET['q'])){$sql.=" AND v.plate_no LIKE ?";$p[]='%'.$_GET['q'].'%';} if(!empty($_GET['status'])){$sql.=" AND b.status=?";$p[]=$_GET['status'];} $sql.=" ORDER BY s.slot_date DESC,s.start_time DESC"; ?>
<div class="card p-3"><div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Vehicle</th><th>Service</th><th>Date / Time</th><th>Mechanic</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach(rows($pdo,$sql,$p) as $b): ?><tr><td><?=$b['booking_id']?></td><td><?=h($b['plate_no'])?></td><td><?=h($b['service'])?></td><td><?=h($b['slot_date'].' '.substr($b['start_time'],0,5))?></td><td><?=h($b['mechanic'])?></td><td><?=h($b['status'])?></td><td><?php if(in_array($b['status'],['PENDING','CONFIRMED'])): ?><form method="post" onsubmit="return confirm('Cancel this booking?')"><input type="hidden" name="action" value="cancel_booking"><input type="hidden" name="booking_id" value="<?=$b['booking_id']?>"><button class="btn btn-sm btn-outline-danger">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<?php elseif($page==='jobs' && in_array(role(),['MECHANIC','MANAGER'],true)): ?>
<h2 class="page-title"><?=role()==='MANAGER'?'All Bookings':'My Job List'?></h2>
<?php if(role()==='MECHANIC'){$jobs=rows($pdo,"SELECT b.*,o.full_name owner,v.plate_no,v.make,v.model,st.name service,s.slot_date,s.start_time,s.end_time FROM `ServiceBooking` b JOIN `Owner` o ON o.owner_id=b.owner_id JOIN `Vehicle` v ON v.vehicle_id=b.vehicle_id JOIN `ServiceType` st ON st.service_type_id=b.service_type_id JOIN `ServiceSlot` s ON s.slot_id=b.slot_id WHERE s.mechanic_id=? ORDER BY s.slot_date,s.start_time",[$u['id']]);}else{$jobs=rows($pdo,"SELECT b.*,o.full_name owner,v.plate_no,v.make,v.model,st.name service,s.slot_date,s.start_time,s.end_time,m.full_name mechanic FROM `ServiceBooking` b JOIN `Owner` o ON o.owner_id=b.owner_id JOIN `Vehicle` v ON v.vehicle_id=b.vehicle_id JOIN `ServiceType` st ON st.service_type_id=b.service_type_id JOIN `ServiceSlot` s ON s.slot_id=b.slot_id JOIN `Mechanic` m ON m.mechanic_id=s.mechanic_id ORDER BY s.slot_date,s.start_time");} ?>
<div class="card p-3"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Booking</th><th>Owner</th><th>Vehicle</th><th>Service</th><th>Schedule</th><?php if(role()==='MANAGER'): ?><th>Mechanic</th><?php endif; ?><th>Status</th><th>Update</th></tr></thead><tbody><?php foreach($jobs as $j): ?><tr><td>#<?=$j['booking_id']?></td><td><?=h($j['owner'])?></td><td><?=h($j['plate_no'].' '.$j['make'].' '.$j['model'])?></td><td><?=h($j['service'])?></td><td><?=h($j['slot_date'].' '.substr($j['start_time'],0,5))?></td><?php if(role()==='MANAGER'): ?><td><?=h($j['mechanic'])?></td><?php endif; ?><td><?=h($j['status'])?></td><td><?php $next=['PENDING'=>'CONFIRMED','CONFIRMED'=>'IN_PROGRESS','IN_PROGRESS'=>'COMPLETED'][$j['status']]??null; if($next): ?><form method="post"><input type="hidden" name="action" value="update_status"><input type="hidden" name="booking_id" value="<?=$j['booking_id']?>"><input type="hidden" name="status" value="<?=$next?>"><button class="btn btn-sm btn-primary">Mark <?=h(str_replace('_',' ',$next))?></button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<?php else: ?><div class="alert alert-warning">That page is not available for your account role.</div><?php endif; ?>
</main></div></div></body></html>
