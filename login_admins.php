<?php 
include 'connection.php'; // ensure you have a working DB connection

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $role = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($role) || empty($username) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Please fill in all fields."]);
        exit;
    }

    // Select query based on role
    if ($role === 'owner') {
        $query = "SELECT * FROM owner WHERE username = ?";
    } elseif ($role === 'supervisor') {
        $query = "SELECT * FROM supervisors WHERE username = ?";
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid role selected."]);
        exit;
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {

            if ($role === 'supervisor') {
                // Use ADMIN_SESSION for supervisor/admin
                session_name('ADMIN_SESSION');
                session_start();
                
                // ✅ Set session values (overwrites any existing data)
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $role;
                $_SESSION['id'] = $row['id'];
                $_SESSION['supervisor_id'] = $row['id'];

                // ✅ Generate a session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // ✅ Save token to database
                $update = $conn->prepare("UPDATE supervisors SET session_token = ? WHERE id = ?");
                $update->bind_param("si", $session_token, $row['id']);
                $update->execute();
                $update->close();

                $redirect = "admin_dashboard.php";
            } else { // ✅ Owner login
                // Use SUPERADMIN_SESSION for owner/superadmin
                session_name('SUPERADMIN_SESSION');
                session_start();
                
                // ✅ Set session values (overwrites any existing data)
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $role;
                $_SESSION['id'] = $row['id'];
                $_SESSION['owner_id'] = $row['id'];

                // ✅ Generate a unique session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // ✅ Save the token in the database
                $update = $conn->prepare("UPDATE owner SET session_token = ? WHERE id = ?");
                $update->bind_param("si", $session_token, $row['id']);
                $update->execute();
                $update->close();

                $redirect = "superadmin_dashboard.php";
            }

            echo json_encode(["status" => "success", "redirect" => $redirect]);

        } else {
            echo json_encode(["status" => "error", "message" => "Incorrect password."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No account found with that username."]);
    }

    $stmt->close();
    $conn->close();
}
?>
