<?php
if (!defined('PUBLIC_ACTIONS_HANDLED')) {
    define('PUBLIC_ACTIONS_HANDLED', true);
}

if (!function_exists('redirect_after_action')) {
    function redirect_after_action($path)
    {
        if (!headers_sent()) {
            header('location:' . $path);
            exit;
        }
        echo "<script type='text/javascript'> document.location = '" . e($path) . "'; </script>";
        exit;
    }
}

if (isset($_POST['signin'])) {
    if (!csrf_verify()) {
        $_SESSION['login_error'] = 'Invalid form token. Please try again.';
    } else {
        $email = $_POST['email'];
        $password = $_POST['password'];
        $sql = "SELECT EmailId,Password FROM tblusers WHERE EmailId=:email LIMIT 1";
        $query = $dbh->prepare($sql);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_OBJ);

        if ($result && app_password_verify($password, $result->Password)) {
            if (app_password_needs_rehash($result->Password)) {
                $newHash = app_password_hash($password);
                $update = $dbh->prepare("UPDATE tblusers SET Password=:password WHERE EmailId=:email");
                $update->bindParam(':password', $newHash, PDO::PARAM_STR);
                $update->bindParam(':email', $email, PDO::PARAM_STR);
                $update->execute();
            }
            $_SESSION['login'] = $result->EmailId;
            redirect_after_action('package-list.php');
        }

        $_SESSION['login_error'] = 'Invalid Details';
    }
}

if (isset($_POST['signup_submit']) || (isset($_POST['submit']) && isset($_POST['fname'], $_POST['mobilenumber'], $_POST['email'], $_POST['password']))) {
    if (!csrf_verify()) {
        $_SESSION['msg'] = 'Invalid form token. Please try again.';
        redirect_after_action('thankyou.php');
    }

    $fname = $_POST['fname'];
    $mnumber = $_POST['mobilenumber'];
    $email = $_POST['email'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['msg'] = 'Please enter a valid email address.';
        redirect_after_action('thankyou.php');
    }

    $checkSql = "SELECT id FROM tblusers WHERE EmailId=:email LIMIT 1";
    $checkQuery = $dbh->prepare($checkSql);
    $checkQuery->bindParam(':email', $email, PDO::PARAM_STR);
    $checkQuery->execute();
    if ($checkQuery->rowCount() > 0) {
        $_SESSION['msg'] = 'Email already exists. Please sign in.';
        redirect_after_action('thankyou.php');
    }

    $password = app_password_hash($_POST['password']);
    $sql = "INSERT INTO tblusers(FullName,MobileNumber,EmailId,Password) VALUES(:fname,:mnumber,:email,:password)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':fname', $fname, PDO::PARAM_STR);
    $query->bindParam(':mnumber', $mnumber, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->bindParam(':password', $password, PDO::PARAM_STR);
    $query->execute();

    $_SESSION['msg'] = $dbh->lastInsertId()
        ? 'You are successfully registered. Now you can login.'
        : 'Something went wrong. Please try again.';
    redirect_after_action('thankyou.php');
}

if (isset($_POST['submit_issue'])) {
    if (!csrf_verify()) {
        $_SESSION['msg'] = 'Invalid form token. Please try again.';
        redirect_after_action('thankyou.php');
    }

    $email = isset($_SESSION['login']) ? $_SESSION['login'] : '';
    if ($email === '') {
        $_SESSION['msg'] = 'Please sign in before submitting an issue.';
        redirect_after_action('thankyou.php');
    }

    $issue = $_POST['issue'];
    $description = $_POST['description'];
    $sql = "INSERT INTO tblissues(UserEmail,Issue,Description) VALUES(:email,:issue,:description)";
    $query = $dbh->prepare($sql);
    $query->bindParam(':issue', $issue, PDO::PARAM_STR);
    $query->bindParam(':description', $description, PDO::PARAM_STR);
    $query->bindParam(':email', $email, PDO::PARAM_STR);
    $query->execute();

    $_SESSION['msg'] = $dbh->lastInsertId()
        ? 'Info successfully submitted.'
        : 'Something went wrong. Please try again.';
    redirect_after_action('thankyou.php');
}
?>
