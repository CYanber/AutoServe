<?php
require_once __DIR__ . '/../config/database.php';

$staff = [
    ['Shop Manager', 'manager@autoserve.test', 'Manager123!', 'MANAGER', '09170000001'],
    ['Juan Mechanic', 'mechanic@autoserve.test', 'Mechanic123!', 'MECHANIC', '09170000002']
];

foreach ($staff as $person) {
    [$name, $email, $password, $role, $phone] = $person;
    $check = $pdo->prepare("SELECT mechanic_id FROM `Mechanic` WHERE email = ?");
    $check->execute([$email]);
    if (!$check->fetch()) {
        $insert = $pdo->prepare(
            "INSERT INTO `Mechanic` (full_name,email,password_hash,role,phone) VALUES (?,?,?,?,?)"
        );
        $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $phone]);
    }
}
echo "Demo staff accounts are ready.<br>";
echo "Manager: manager@autoserve.test / Manager123!<br>";
echo "Mechanic: mechanic@autoserve.test / Mechanic123!<br>";
echo "Delete or rename this setup file after creating the demo accounts.";
