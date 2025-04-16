<?php
// Include database connection
require_once 'db.php';

// Start session
session_start();

// Define pages
$pages = [
    'home' => 'Home',
    'login' => 'Login',
    'register' => 'Register',
    'profile' => 'Profile',
    'post-job' => 'Post a Job',
    'job-details' => 'Job Details',
    'my-applications' => 'My Applications',
    'my-jobs' => 'My Jobs',
    'logout' => 'Logout'
];

// Get current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Handle user authentication
$is_logged_in = isset($_SESSION['user_id']);
$user_type = $is_logged_in ? $_SESSION['user_type'] : '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login form submission
    if (isset($_POST['login'])) {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        $sql = "SELECT user_id, username, email, password, user_type FROM users WHERE email = '$email'";
        $user = getRow($sql);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Redirect to home page
            echo "<script>window.location.href = 'index.php';</script>";
            exit;
        } else {
            $login_error = "Invalid email or password";
        }
    }
    
    // Registration form submission
    if (isset($_POST['register'])) {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $user_type = sanitize($_POST['user_type']);
        
        // Check if email already exists
        $check_sql = "SELECT user_id FROM users WHERE email = '$email' OR username = '$username'";
        $existing_user = getRow($check_sql);
        
        if ($existing_user) {
            $register_error = "Email or username already exists";
        } else {
            $sql = "INSERT INTO users (username, email, password, first_name, last_name, user_type) 
                    VALUES ('$username', '$email', '$password', '$first_name', '$last_name', '$user_type')";
            $user_id = insertData($sql);
            
            if ($user_id) {
                // Create profile based on user type
                if ($user_type === 'job_seeker') {
                    $profile_sql = "INSERT INTO job_seeker_profiles (user_id) VALUES ($user_id)";
                    insertData($profile_sql);
                } else if ($user_type === 'employer') {
                    $company_name = sanitize($_POST['company_name']);
                    $profile_sql = "INSERT INTO employer_profiles (user_id, company_name) VALUES ($user_id, '$company_name')";
                    insertData($profile_sql);
                }
                
                // Auto login after registration
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['user_type'] = $user_type;
                
                // Redirect to home page
                echo "<script>window.location.href = 'index.php';</script>";
                exit;
            } else {
                $register_error = "Registration failed";
            }
        }
    }
    
    // Profile update form submission
    if (isset($_POST['update_profile']) && $is_logged_in) {
        $user_id = $_SESSION['user_id'];
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        
        $sql = "UPDATE users SET first_name = '$first_name', last_name = '$last_name', 
                phone = '$phone', address = '$address' WHERE user_id = $user_id";
        executeQuery($sql);
        
        if ($user_type === 'job_seeker') {
            $headline = sanitize($_POST['headline']);
            $summary = sanitize($_POST['summary']);
            $experience_years = (int)$_POST['experience_years'];
            $education_level = sanitize($_POST['education_level']);
            $skills = sanitize($_POST['skills']);
            
            // Handle resume upload
            $resume_path = null;
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/resumes/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $user_id . '_' . time() . '_' . basename($_FILES['resume']['name']);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
                    $resume_path = $target_file;
                }
            }
            
            $profile_sql = "UPDATE job_seeker_profiles SET headline = '$headline', summary = '$summary', 
                            experience_years = $experience_years, education_level = '$education_level', 
                            skills = '$skills'";
            
            if ($resume_path) {
                $profile_sql .= ", resume_path = '$resume_path'";
            }
            
            $profile_sql .= " WHERE user_id = $user_id";
            executeQuery($profile_sql);
        } else if ($user_type === 'employer') {
            $company_name = sanitize($_POST['company_name']);
            $industry = sanitize($_POST['industry']);
            $company_size = sanitize($_POST['company_size']);
            $company_description = sanitize($_POST['company_description']);
            $website = sanitize($_POST['website']);
            
            // Handle company logo upload
            $logo_path = null;
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/logos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $user_id . '_' . time() . '_' . basename($_FILES['company_logo']['name']);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_file)) {
                    $logo_path = $target_file;
                }
            }
            
            $profile_sql = "UPDATE employer_profiles SET company_name = '$company_name', industry = '$industry', 
                            company_size = '$company_size', company_description = '$company_description', 
                            website = '$website'";
            
            if ($logo_path) {
                $profile_sql .= ", company_logo = '$logo_path'";
            }
            
            $profile_sql .= " WHERE user_id = $user_id";
            executeQuery($profile_sql);
        }
        
        $profile_updated = true;
    }
    
    // Post job form submission
    if (isset($_POST['post_job']) && $is_logged_in && $user_type === 'employer') {
        $employer_id = $_SESSION['user_id'];
        $category_id = (int)$_POST['category_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $requirements = sanitize($_POST['requirements']);
        $location = sanitize($_POST['location']);
        $job_type = sanitize($_POST['job_type']);
        $salary_min = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : 'NULL';
        $salary_max = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : 'NULL';
        
        $sql = "INSERT INTO jobs (employer_id, category_id, title, description, requirements, location, job_type, salary_min, salary_max) 
                VALUES ($employer_id, $category_id, '$title', '$description', '$requirements', '$location', '$job_type', $salary_min, $salary_max)";
        $job_id = insertData($sql);
        
        if ($job_id) {
            $job_posted = true;
            // Redirect to job details page
            echo "<script>window.location.href = 'index.php?page=job-details&id=$job_id';</script>";
            exit;
        } else {
            $job_error = "Failed to post job";
        }
    }
    
    // Job application form submission
    if (isset($_POST['apply_job']) && $is_logged_in && $user_type === 'job_seeker') {
        $user_id = $_SESSION['user_id'];
        $job_id = (int)$_POST['job_id'];
        $cover_letter = sanitize($_POST['cover_letter']);
        
        // Check if already applied
        $check_sql = "SELECT application_id FROM applications WHERE job_id = $job_id AND user_id = $user_id";
        $existing_application = getRow($check_sql);
        
        if ($existing_application) {
            $application_error = "You have already applied for this job";
        } else {
            $sql = "INSERT INTO applications (job_id, user_id, cover_letter) 
                    VALUES ($job_id, $user_id, '$cover_letter')";
            $application_id = insertData($sql);
            
            if ($application_id) {
                $application_success = true;
                // Redirect to my applications page
                echo "<script>window.location.href = 'index.php?page=my-applications';</script>";
                exit;
            } else {
                $application_error = "Failed to submit application";
            }
        }
    }
    
    // Save job form submission
    if (isset($_POST['save_job']) && $is_logged_in) {
        $user_id = $_SESSION['user_id'];
        $job_id = (int)$_POST['job_id'];
        
        // Check if already saved
        $check_sql = "SELECT saved_id FROM saved_jobs WHERE job_id = $job_id AND user_id = $user_id";
        $existing_saved = getRow($check_sql);
        
        if (!$existing_saved) {
            $sql = "INSERT INTO saved_jobs (job_id, user_id) VALUES ($job_id, $user_id)";
            insertData($sql);
        }
        
        // Redirect back to the job details page
        echo "<script>window.location.href = 'index.php?page=job-details&id=$job_id';</script>";
        exit;
    }
    
    // Unsave job form submission
    if (isset($_POST['unsave_job']) && $is_logged_in) {
        $user_id = $_SESSION['user_id'];
        $job_id = (int)$_POST['job_id'];
        
        $sql = "DELETE FROM saved_jobs WHERE job_id = $job_id AND user_id = $user_id";
        executeQuery($sql);
        
        // Redirect back to the job details page
        echo "<script>window.location.href = 'index.php?page=job-details&id=$job_id';</script>";
        exit;
    }
    
    // Update application status form submission
    if (isset($_POST['update_application']) && $is_logged_in && $user_type === 'employer') {
        $application_id = (int)$_POST['application_id'];
        $status = sanitize($_POST['status']);
        
        $sql = "UPDATE applications SET status = '$status' WHERE application_id = $application_id";
        executeQuery($sql);
        
        // Redirect back to the job applications page
        $job_id = (int)$_POST['job_id'];
        echo "<script>window.location.href = 'index.php?page=job-applications&id=$job_id';</script>";
        exit;
    }
}

// Handle logout
if ($current_page === 'logout' && $is_logged_in) {
    session_unset();
    session_destroy();
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobMonster - Find Your Dream Job</title>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        a {
            text-decoration: none;
            color: #2557a7;
            transition: color 0.3s;
        }
        
        a:hover {
            color: #1e3c72;
        }
        
        ul {
            list-style: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2557a7;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #1e3c72;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2557a7;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mt-3 {
            margin-top: 15px;
        }
        
        .mb-3 {
            margin-bottom: 15px;
        }
        
        .p-3 {
            padding: 15px;
        }
        
        /* Header Styles */
        header {
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2557a7;
        }
        
        .logo span {
            color: #333;
        }
        
        nav ul {
            display: flex;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            color: #333;
            font-weight: 500;
        }
        
        nav ul li a:hover {
            color: #2557a7;
        }
        
        .mobile-menu-btn {
            display: none;
            font-size: 24px;
            cursor: pointer;
        }
        
        /* Hero Section Styles */
        .hero {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            padding: 80px 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .search-form {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            background-color: #fff;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .search-form input {
            flex: 1;
            padding: 15px;
            border: none;
            font-size: 16px;
        }
        
        .search-form select {
            padding: 15px;
            border: none;
            border-left: 1px solid #ddd;
            font-size: 16px;
            width: 200px;
        }
        
        .search-form button {
            padding: 15px 30px;
            background-color: #2557a7;
            color: #fff;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .search-form button:hover {
            background-color: #1e3c72;
        }
        
        /* Job Listings Styles */
        .section {
            padding: 60px 0;
        }
        
        .section-title {
            font-size: 32px;
            margin-bottom: 40px;
            text-align: center;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background-color: #2557a7;
            margin: 15px auto 0;
        }
        
        .job-filters {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 200px;
        }
        
        .job-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .job-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .job-card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .company-logo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #2557a7;
            font-size: 20px;
        }
        
        .job-card-title {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .company-name {
            color: #666;
            font-size: 14px;
        }
        
        .job-card-body {
            padding: 20px;
        }
        
        .job-info {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .job-info-item {
            margin-right: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        
        .job-info-item i {
            margin-right: 5px;
            color: #2557a7;
        }
        
        .job-description {
            color: #666;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .job-card-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .job-type {
            display: inline-block;
            padding: 5px 10px;
            background-color: #e6f0ff;
            color: #2557a7;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .job-posted {
            color: #666;
            font-size: 14px;
        }
        
        /* Categories Section Styles */
        .categories {
            background-color: #fff;
        }
        
        .category-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .category-card {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .category-icon {
            font-size: 40px;
            color: #2557a7;
            margin-bottom: 15px;
        }
        
        .category-title {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .job-count {
            color: #666;
            font-size: 14px;
        }
        
        /* Featured Employers Section Styles */
        .employers {
            background-color: #f9f9f9;
        }
        
        .employer-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 30px;
        }
        
        .employer-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .employer-card:hover {
            transform: translateY(-5px);
        }
        
        .employer-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #2557a7;
            font-size: 24px;
        }
        
        .employer-name {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .employer-industry {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        /* Form Styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .form-title {
            font-size: 24px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-col {
            flex: 1;
            padding: 0 10px;
            min-width: 250px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-submit {
            margin-top: 20px;
        }
        
        /* Profile Styles */
        .profile-header {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
        }
        
        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 30px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #2557a7;
            font-size: 36px;
        }
        
        .profile-info {
            flex: 1;
            min-width: 250px;
        }
        
        .profile-name {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .profile-headline {
            color: #666;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .profile-details {
            display: flex;
            flex-wrap: wrap;
        }
        
        .profile-detail {
            margin-right: 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            color: #666;
        }
        
        .profile-detail i {
            margin-right: 5px;
            color: #2557a7;
        }
        
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
        }
        
        .profile-tab {
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .profile-tab.active {
            color: #2557a7;
            border-bottom-color: #2557a7;
        }
        
        .profile-content {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-section-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Job Details Styles */
        .job-details-header {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
        }
        
        .job-details-company-logo {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 30px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #2557a7;
            font-size: 30px;
        }
        
        .job-details-info {
            flex: 1;
            min-width: 250px;
        }
        
        .job-details-title {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .job-details-company {
            color: #666;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .job-details-meta {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .job-details-meta-item {
            margin-right: 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            color: #666;
        }
        
        .job-details-meta-item i {
            margin-right: 5px;
            color: #2557a7;
        }
        
        .job-details-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .job-details-content {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .job-details-section {
            margin-bottom: 30px;
        }
        
        .job-details-section-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Footer Styles */
        footer {
            background-color: #1e3c72;
            color: #fff;
            padding: 60px 0 30px;
        }
        
        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .footer-logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .footer-description {
            margin-bottom: 20px;
            color: #ccc;
        }
        
        .footer-social {
            display: flex;
            gap: 15px;
        }
        
        .footer-social a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: #fff;
            transition: background-color 0.3s;
        }
        
        .footer-social a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .footer-title {
            font-size: 18px;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 2px;
            background-color: #2557a7;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #ccc;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: #fff;
        }
        
        .footer-contact-item {
            display: flex;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .footer-contact-item i {
            margin-right: 10px;
            color: #2557a7;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #ccc;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .hero h1 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 18px;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-form input,
            .search-form select,
            .search-form button {
                width: 100%;
                border-radius: 0;
            }
            
            .search-form select {
                border-left: none;
                border-top: 1px solid #ddd;
            }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            nav {
                margin-top: 20px;
            }
            
            nav ul {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul li {
                margin: 10px 0;
            }
            
            .mobile-menu-btn {
                display: block;
                position: absolute;
                top: 20px;
                right: 20px;
            }
            
            .job-details-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .job-details-company-logo {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .job-details-meta {
                justify-content: center;
            }
            
            .job-details-actions {
                justify-content: center;
            }
            
            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .profile-image {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .profile-details {
                justify-content: center;
            }
        }
        
        /* Icons */
        .icon {
            display: inline-block;
            width: 1em;
            height: 1em;
            stroke-width: 0;
            stroke: currentColor;
            fill: currentColor;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">Job<span>Monster</span></a>
            <div class="mobile-menu-btn">☰</div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php?page=jobs">Jobs</a></li>
                    <li><a href="index.php?page=employers">Employers</a></li>
                    <?php if ($is_logged_in): ?>
                        <?php if ($user_type === 'job_seeker'): ?>
                            <li><a href="index.php?page=my-applications">My Applications</a></li>
                        <?php elseif ($user_type === 'employer'): ?>
                            <li><a href="index.php?page=post-job">Post a Job</a></li>
                            <li><a href="index.php?page=my-jobs">My Jobs</a></li>
                        <?php endif; ?>
                        <li><a href="index.php?page=profile">Profile</a></li>
                        <li><a href="index.php?page=logout">Logout</a></li>
                    <?php else: ?>
                        <li><a href="index.php?page=login">Login</a></li>
                        <li><a href="index.php?page=register">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- Main Content -->
    <main>
        <?php
        // Display page content based on current page
        switch ($current_page) {
            case 'home':
                include 'pages/home.php';
                break;
            case 'login':
                include 'pages/login.php';
                break;
            case 'register':
                include 'pages/register.php';
                break;
            case 'profile':
                if ($is_logged_in) {
                    include 'pages/profile.php';
                } else {
                    echo "<script>window.location.href = 'index.php?page=login';</script>";
                }
                break;
            case 'post-job':
                if ($is_logged_in && $user_type === 'employer') {
                    include 'pages/post-job.php';
                } else {
                    echo "<script>window.location.href = 'index.php?page=login';</script>";
                }
                break;
            case 'job-details':
                include 'pages/job-details.php';
                break;
            case 'my-applications':
                if ($is_logged_in && $user_type === 'job_seeker') {
                    include 'pages/my-applications.php';
                } else {
                    echo "<script>window.location.href = 'index.php?page=login';</script>";
                }
                break;
            case 'my-jobs':
                if ($is_logged_in && $user_type === 'employer') {
                    include 'pages/my-jobs.php';
                } else {
                    echo "<script>window.location.href = 'index.php?page=login';</script>";
                }
                break;
            case 'job-applications':
                if ($is_logged_in && $user_type === 'employer') {
                    include 'pages/job-applications.php';
                } else {
                    echo "<script>window.location.href = 'index.php?page=login';</script>";
                }
                break;
            case 'jobs':
                include 'pages/jobs.php';
                break;
            case 'employers':
                include 'pages/employers.php';
                break;
            default:
                include 'pages/home.php';
                break;
        }
        ?>
    </main>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div>
                    <div class="footer-logo">Job<span>Monster</span></div>
                    <p class="footer-description">
                        JobMonster is a leading job portal that connects job seekers with employers.
                        Find your dream job or hire the best talent for your company.
                    </p>
                    <div class="footer-social">
                        <a href="#"><span class="icon">F</span></a>
                        <a href="#"><span class="icon">T</span></a>
                        <a href="#"><span class="icon">L</span></a>
                        <a href="#"><span class="icon">I</span></a>
                    </div>
                </div>
                
                <div>
                    <h3 class="footer-title">Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php?page=jobs">Browse Jobs</a></li>
                        <li><a href="index.php?page=employers">Employers</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="footer-title">Job Seekers</h3>
                    <ul class="footer-links">
                        <li><a href="index.php?page=register">Create Account</a></li>
                        <li><a href="index.php?page=jobs">Browse Jobs</a></li>
                        <li><a href="#">Job Alerts</a></li>
                        <li><a href="#">Career Advice</a></li>
                        <li><a href="#">Resume Tips</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="footer-title">Contact Us</h3>
                    <div class="footer-contact-item">
                        <span class="icon">📍</span>
                        <span>123 Job Street, Employment City, 12345</span>
                    </div>
                    <div class="footer-contact-item">
                        <span class="icon">📞</span>
                        <span>+1 (123) 456-7890</span>
                    </div>
                    <div class="footer-contact-item">
                        <span class="icon">✉️</span>
                        <span>info@jobmonster.com</span>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> JobMonster. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('active');
        });
        
        // Profile tabs functionality
        const profileTabs = document.querySelectorAll('.profile-tab');
        const profileContents = document.querySelectorAll('.profile-content-item');
        
        if (profileTabs.length > 0 && profileContents.length > 0) {
            profileTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    profileTabs.forEach(t => t.classList.remove('active'));
                    profileContents.forEach(c => c.style.display = 'none');
                    
                    // Add active class to clicked tab
                    tab.classList.add('active');
                    
                    // Show corresponding content
                    const target = tab.getAttribute('data-target');
                    document.getElementById(target).style.display = 'block';
                });
            });
            
            // Set default active tab
            profileTabs[0].click();
        }
        
        // Form validation
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    event.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
        
        // File input preview
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                const previewId = this.getAttribute('data-preview');
                if (previewId && this.files && this.files[0]) {
                    const preview = document.getElementById(previewId);
                    if (preview) {
                        preview.textContent = this.files[0].name;
                    }
                }
            });
        });
        
        // Redirect function
        function redirectTo(url) {
            window.location.href = url;
        }
    </script>
</body>
</html>

<?php
// Create pages directory if it doesn't exist
if (!file_exists('pages')) {
    mkdir('pages', 0777, true);
}

// Create home page
$home_page = <<<'EOT'
<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <h1>Find Your Dream Job Today</h1>
        <p>Browse thousands of job listings and find the perfect match for your skills and experience.</p>
        
        <form class="search-form" action="index.php" method="GET">
            <input type="hidden" name="page" value="jobs">
            <input type="text" name="keyword" placeholder="Job title, keywords, or company">
            <select name="location">
                <option value="">All Locations</option>
                <option value="New York">New York</option>
                <option value="San Francisco">San Francisco</option>
                <option value="Chicago">Chicago</option>
                <option value="Los Angeles">Los Angeles</option>
                <option value="Remote">Remote</option>
            </select>
            <button type="submit">Search Jobs</button>
        </form>
    </div>
</section>

<!-- Featured Jobs Section -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Featured Jobs</h2>
        
        <div class="job-list">
            <?php
            // Get featured jobs
            $sql = "SELECT j.*, c.category_name, u.username, e.company_name, e.company_logo 
                    FROM jobs j 
                    JOIN job_categories c ON j.category_id = c.category_id 
                    JOIN users u ON j.employer_id = u.user_id 
                    JOIN employer_profiles e ON j.employer_id = e.user_id 
                    WHERE j.is_active = 1 
                    ORDER BY j.created_at DESC 
                    LIMIT 6";
            $jobs = getRows($sql);
            
            if (count($jobs) > 0) {
                foreach ($jobs as $job) {
                    // Calculate time ago
                    $created_date = new DateTime($job['created_at']);
                    $now = new DateTime();
                    $interval = $created_date->diff($now);
                    
                    if ($interval->d > 0) {
                        $time_ago = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->h > 0) {
                        $time_ago = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                    } else {
                        $time_ago = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                    }
                    
                    // Format job type
                    $job_type_formatted = ucwords(str_replace('_', ' ', $job['job_type']));
                    
                    // Get company logo or first letter
                    $company_logo = $job['company_logo'] ? $job['company_logo'] : substr($job['company_name'], 0, 1);
                    
                    // Format salary range
                    $salary_range = '';
                    if ($job['salary_min'] && $job['salary_max']) {
                        $salary_range = '$' . number_format($job['salary_min']) . ' - $' . number_format($job['salary_max']);
                    } elseif ($job['salary_min']) {
                        $salary_range = 'From $' . number_format($job['salary_min']);
                    } elseif ($job['salary_max']) {
                        $salary_range = 'Up to $' . number_format($job['salary_max']);
                    } else {
                        $salary_range = 'Not specified';
                    }
                    
                    echo <<<HTML
                    <div class="job-card">
                        <div class="job-card-header">
                            <div class="company-logo">{$company_logo}</div>
                            <div>
                                <h3 class="job-card-title">{$job['title']}</h3>
                                <div class="company-name">{$job['company_name']}</div>
                            </div>
                        </div>
                        <div class="job-card-body">
                            <div class="job-info">
                                <div class="job-info-item">
                                    <span class="icon">📍</span> {$job['location']}
                                </div>
                                <div class="job-info-item">
                                    <span class="icon">💼</span> {$job['category_name']}
                                </div>
                                <div class="job-info-item">
                                    <span class="icon">💰</span> {$salary_range}
                                </div>
                            </div>
                            <div class="job-description">{$job['description']}</div>
                        </div>
                        <div class="job-card-footer">
                            <div class="job-type">{$job_type_formatted}</div>
                            <div class="job-posted">{$time_ago}</div>
                        </div>
                        <a href="index.php?page=job-details&id={$job['job_id']}" class="btn" style="display: block; margin: 15px; text-align: center;">View Details</a>
                    </div>
HTML;
                }
            } else {
                echo '<p class="text-center">No jobs found.</p>';
            }
            ?>
        </div>
        
        <div class="text-center mt-3">
            <a href="index.php?page=jobs" class="btn">View All Jobs</a>
        </div>
    </div>
</section>

<!-- Job Categories Section -->
<section class="section categories">
    <div class="container">
        <h2 class="section-title">Popular Job Categories</h2>
        
        <div class="category-list">
            <?php
            // Get job categories with job count
            $sql = "SELECT c.category_id, c.category_name, COUNT(j.job_id) as job_count 
                    FROM job_categories c 
                    LEFT JOIN jobs j ON c.category_id = j.category_id AND j.is_active = 1 
                    GROUP BY c.category_id 
                    ORDER BY job_count DESC 
                    LIMIT 8";
            $categories = getRows($sql);
            
            if (count($categories) > 0) {
                foreach ($categories as $category) {
                    // Get icon based on category name
                    $icon = '💼';
                    switch (strtolower($category['category_name'])) {
                        case 'information technology':
                            $icon = '💻';
                            break;
                        case 'marketing':
                            $icon = '📊';
                            break;
                        case 'sales':
                            $icon = '📈';
                            break;
                        case 'finance':
                            $icon = '💰';
                            break;
                        case 'healthcare':
                            $icon = '🏥';
                            break;
                        case 'education':
                            $icon = '🎓';
                            break;
                        case 'engineering':
                            $icon = '🔧';
                            break;
                        case 'customer service':
                            $icon = '🤝';
                            break;
                        case 'human resources':
                            $icon = '👥';
                            break;
                        case 'administrative':
                            $icon = '📋';
                            break;
                    }
                    
                    echo <<<HTML
                    <div class="category-card">
                        <div class="category-icon">{$icon}</div>
                        <h3 class="category-title">{$category['category_name']}</h3>
                        <div class="job-count">{$category['job_count']} Jobs</div>
                    </div>
HTML;
                }
            } else {
                echo '<p class="text-center">No categories found.</p>';
            }
            ?>
        </div>
    </div>
</section>

<!-- Featured Employers Section -->
<section class="section employers">
    <div class="container">
        <h2 class="section-title">Featured Employers</h2>
        
        <div class="employer-list">
            <?php
            // Get featured employers
            $sql = "SELECT e.*, u.username, COUNT(j.job_id) as job_count 
                    FROM employer_profiles e 
                    JOIN users u ON e.user_id = u.user_id 
                    LEFT JOIN jobs j ON e.user_id = j.employer_id AND j.is_active = 1 
                    GROUP BY e.user_id 
                    ORDER BY job_count DESC 
                    LIMIT 8";
            $employers = getRows($sql);
            
            if (count($employers) > 0) {
                foreach ($employers as $employer) {
                    // Get company logo or first letter
                    $company_logo = $employer['company_logo'] ? $employer['company_logo'] : substr($employer['company_name'], 0, 1);
                    
                    echo <<<HTML
                    <div class="employer-card">
                        <div class="employer-logo">{$company_logo}</div>
                        <h3 class="employer-name">{$employer['company_name']}</h3>
                        <div class="employer-industry">{$employer['industry'] ?: 'Various Industries'}</div>
                        <div>{$employer['job_count']} Active Jobs</div>
                    </div>
HTML;
                }
            } else {
                echo '<p class="text-center">No employers found.</p>';
            }
            ?>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="section" style="background-color: #f0f5ff; padding: 80px 0;">
    <div class="container text-center">
        <h2 style="font-size: 32px; margin-bottom: 20px;">Ready to Take the Next Step in Your Career?</h2>
        <p style="font-size: 18px; max-width: 700px; margin: 0 auto 30px;">
            Join thousands of job seekers who have found their dream jobs through JobMonster.
            Create your profile today and start applying to top jobs.
        </p>
        <div>
            <?php if (!$is_logged_in): ?>
                <a href="index.php?page=register" class="btn" style="margin-right: 15px;">Create Account</a>
                <a href="index.php?page=jobs" class="btn btn-secondary">Browse Jobs</a>
            <?php else: ?>
                <a href="index.php?page=jobs" class="btn">Browse Jobs</a>
            <?php endif; ?>
        </div>
    </div>
</section>
EOT;
file_put_contents('pages/home.php', $home_page);

// Create login page
$login_page = <<<'EOT'
<section class="section">
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Login to Your Account</h2>
            
            <?php if (isset($login_error)): ?>
                <div class="alert alert-danger"><?php echo $login_error; ?></div>
            <?php endif; ?>
            
            <form action="index.php?page=login" method="POST">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="login" class="btn">Login</button>
                </div>
                
                <div class="text-center">
                    <p>Don't have an account? <a href="index.php?page=register">Register</a></p>
                </div>
            </form>
        </div>
    </div>
</section>
EOT;
file_put_contents('pages/login.php', $login_page);

// Create register page
$register_page = <<<'EOT'
<section class="section">
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Create an Account</h2>
            
            <?php if (isset($register_error)): ?>
                <div class="alert alert-danger"><?php echo $register_error; ?></div>
            <?php endif; ?>
            
            <form action="index.php?page=register" method="POST">
                <div class="form-group">
                    <label for="user_type" class="form-label">I am a:</label>
                    <select id="user_type" name="user_type" class="form-control" required onchange="toggleCompanyField()">
                        <option value="job_seeker">Job Seeker</option>
                        <option value="employer">Employer</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div id="company_field" class="form-group" style="display: none;">
                    <label for="company_name" class="form-label">Company Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="register" class="btn">Register</button>
                </div>
                
                <div class="text-center">
                    <p>Already have an account? <a href="index.php?page=login">Login</a></p>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
    function toggleCompanyField() {
        const userType = document.getElementById('user_type').value;
        const companyField = document.getElementById('company_field');
        const companyNameInput = document.getElementById('company_name');
        
        if (userType === 'employer') {
            companyField.style.display = 'block';
            companyNameInput.required = true;
        } else {
            companyField.style.display = 'none';
            companyNameInput.required = false;
        }
    }
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(event) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            event.preventDefault();
            alert('Passwords do not match.');
        }
    });
    
    // Initialize company field visibility
    toggleCompanyField();
</script>
EOT;
file_put_contents('pages/register.php', $register_page);

// Create profile page
$profile_page = <<<'EOT'
<?php
// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE user_id = $user_id";
$user = getRow($sql);

// Get profile data based on user type
if ($user_type === 'job_seeker') {
    $profile_sql = "SELECT * FROM job_seeker_profiles WHERE user_id = $user_id";
    $profile = getRow($profile_sql);
    
    // Get applications
    $applications_sql = "SELECT a.*, j.title, j.location, j.job_type, e.company_name 
                        FROM applications a 
                        JOIN jobs j ON a.job_id = j.job_id 
                        JOIN employer_profiles e ON j.employer_id = e.user_id 
                        WHERE a.user_id = $user_id 
                        ORDER BY a.created_at DESC";
    $applications = getRows($applications_sql);
    
    // Get saved jobs
    $saved_jobs_sql = "SELECT s.*, j.title, j.location, j.job_type, e.company_name 
                      FROM saved_jobs s 
                      JOIN jobs j ON s.job_id = j.job_id 
                      JOIN employer_profiles e ON j.employer_id = e.user_id 
                      WHERE s.user_id = $user_id 
                      ORDER BY s.created_at DESC";
    $saved_jobs = getRows($saved_jobs_sql);
} else if ($user_type === 'employer') {
    $profile_sql = "SELECT * FROM employer_profiles WHERE user_id = $user_id";
    $profile = getRow($profile_sql);
    
    // Get posted jobs
    $jobs_sql = "SELECT j.*, c.category_name, COUNT(a.application_id) as application_count 
                FROM jobs j 
                JOIN job_categories c ON j.category_id = c.category_id 
                LEFT JOIN applications a ON j.job_id = a.job_id 
                WHERE j.employer_id = $user_id 
                GROUP BY j.job_id 
                ORDER BY j.created_at DESC";
    $jobs = getRows($jobs_sql);
}
?>

<section class="section">
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-image">
                <?php echo substr($user['first_name'], 0, 1); ?>
            </div>
            
            <div class="profile-info">
                <h2 class="profile-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h2>
                
                <?php if ($user_type === 'job_seeker' && isset($profile['headline'])): ?>
                    <div class="profile-headline"><?php echo $profile['headline']; ?></div>
                <?php elseif ($user_type === 'employer' && isset($profile['company_name'])): ?>
                    <div class="profile-headline"><?php echo $profile['company_name']; ?></div>
                <?php endif; ?>
                
                <div class="profile-details">
                    <div class="profile-detail">
                        <span class="icon">✉️</span> <?php echo $user['email']; ?>
                    </div>
                    
                    <?php if ($user['phone']): ?>
                        <div class="profile-detail">
                            <span class="icon">📞</span> <?php echo $user['phone']; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($user['address']): ?>
                        <div class="profile-detail">
                            <span class="icon">📍</span> <?php echo $user['address']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <div class="profile-tab active" data-target="profile-edit">Edit Profile</div>
            
            <?php if ($user_type === 'job_seeker'): ?>
                <div class="profile-tab" data-target="applications">My Applications</div>
                <div class="profile-tab" data-target="saved-jobs">Saved Jobs</div>
            <?php elseif ($user_type === 'employer'): ?>
                <div class="profile-tab" data-target="posted-jobs">Posted Jobs</div>
            <?php endif; ?>
        </div>
        
        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Edit Profile Tab -->
            <div id="profile-edit" class="profile-content-item">
                <?php if (isset($profile_updated)): ?>
                    <div class="alert alert-success">Profile updated successfully!</div>
                <?php endif; ?>
                
                <form action="index.php?page=profile" method="POST" enctype="multipart/form-data">
                    <h3 class="profile-section-title">Personal Information</h3>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo $user['first_name']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo $user['last_name']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo $user['phone']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" id="address" name="address" class="form-control" value="<?php echo $user['address']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user_type === 'job_seeker'): ?>
                        <h3 class="profile-section-title">Professional Information</h3>
                        
                        <div class="form-group">
                            <label for="headline" class="form-label">Professional Headline</label>
                            <input type="text" id="headline" name="headline" class="form-control" value="<?php echo isset($profile['headline']) ? $profile['headline'] : ''; ?>" placeholder="e.g., Senior Software Engineer">
                        </div>
                        
                        <div class="form-group">
                            <label for="summary" class="form-label">Professional Summary</label>
                            <textarea id="summary" name="summary" class="form-control" rows="4" placeholder="Brief overview of your professional background"><?php echo isset($profile['summary']) ? $profile['summary'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="experience_years" class="form-label">Years of Experience</label>
                                    <input type="number" id="experience_years" name="experience_years" class="form-control" value="<?php echo isset($profile['experience_years']) ? $profile['experience_years'] : '0'; ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="education_level" class="form-label">Education Level</label>
                                    <select id="education_level" name="education_level" class="form-control">
                                        <option value="">Select Education Level</option>
                                        <option value="High School" <?php echo (isset($profile['education_level']) && $profile['education_level'] === 'High School') ? 'selected' : ''; ?>>High School</option>
                                        <option value="Associate's Degree" <?php echo (isset($profile['education_level']) && $profile['education_level'] === "Associate's Degree") ? 'selected' : ''; ?>>Associate's Degree</option>
                                        <option value="Bachelor's Degree" <?php echo (isset($profile['education_level']) && $profile['education_level'] === "Bachelor's Degree") ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                        <option value="Master's Degree" <?php echo (isset($profile['education_level']) && $profile['education_level'] === "Master's Degree") ? 'selected' : ''; ?>>Master's Degree</option>
                                        <option value="Doctorate" <?php echo (isset($profile['education_level']) && $profile['education_level'] === 'Doctorate') ? 'selected' : ''; ?>>Doctorate</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="skills" class="form-label">Skills</label>
                            <textarea id="skills" name="skills" class="form-control" rows="3" placeholder="List your skills separated by commas"><?php echo isset($profile['skills']) ? $profile['skills'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="resume" class="form-label">Resume</label>
                            <input type="file" id="resume" name="resume" class="form-control" data-preview="resume-preview" accept=".pdf,.doc,.docx">
                            <div id="resume-preview" class="mt-3">
                                <?php echo isset($profile['resume_path']) ? basename($profile['resume_path']) : 'No resume uploaded'; ?>
                            </div>
                        </div>
                    <?php elseif ($user_type === 'employer'): ?>
                        <h3 class="profile-section-title">Company Information</h3>
                        
                        <div class="form-group">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" id="company_name" name="company_name" class="form-control" value="<?php echo isset($profile['company_name']) ? $profile['company_name'] : ''; ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="industry" class="form-label">Industry</label>
                                    <input type="text" id="industry" name="industry" class="form-control" value="<?php echo isset($profile['industry']) ? $profile['industry'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="company_size" class="form-label">Company Size</label>
                                    <select id="company_size" name="company_size" class="form-control">
                                        <option value="">Select Company Size</option>
                                        <option value="1-10" <?php echo (isset($profile['company_size']) && $profile['company_size'] === '1-10') ? 'selected' : ''; ?>>1-10 employees</option>
                                        <option value="11-50" <?php echo (isset($profile['company_size']) && $profile['company_size'] === '11-50') ? 'selected' : ''; ?>>11-50 employees</option>
                                        <option value="51-200" <?php echo (isset($profile['company_size']) && $profile['company_size'] === '51-200') ? 'selected' : ''; ?>>51-200 employees</option>
                                        <option value="201-500" <?php echo (isset($profile['company_size']) && $profile['company_size'] === '201-500') ? 'selected' : ''; ?>>201-500 employees</option>
                                        <option value="501-1000" <?php echo (isset($profile['company_size']) && $profile['company_size'] === '501-1000') ? 'selected' : ''; ?>>501-1000 employees</option>
                                        <option value="1000+" <?php echo (isset($profile['company_size']) && $profile['company_size'] === '1000+') ? 'selected' : ''; ?>>1000+ employees</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="company_description" class="form-label">Company Description</label>
                            <textarea id="company_description" name="company_description" class="form-control" rows="4"><?php echo isset($profile['company_description']) ? $profile['company_description'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" id="website" name="website" class="form-control" value="<?php echo isset($profile['website']) ? $profile['website'] : ''; ?>" placeholder="https://example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="company_logo" class="form-label">Company Logo</label>
                            <input type="file" id="company_logo" name="company_logo" class="form-control" data-preview="logo-preview" accept=".jpg,.jpeg,.png,.gif">
                            <div id="logo-preview" class="mt-3">
                                <?php echo isset($profile['company_logo']) ? basename($profile['company_logo']) : 'No logo uploaded'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group form-submit">
                        <button type="submit" name="update_profile" class="btn">Update Profile</button>
                    </div>
                </form>
            </div>
            
            <?php if ($user_type === 'job_seeker'): ?>
                <!-- Applications Tab -->
                <div id="applications" class="profile-content-item" style="display: none;">
                    <h3 class="profile-section-title">My Job Applications</h3>
                    
                    <?php if (count($applications) > 0): ?>
                        <div class="job-list" style="display: block;">
                            <?php foreach ($applications as $application): ?>
                                <?php
                                // Format job type
                                $job_type_formatted = ucwords(str_replace('_', ' ', $application['job_type']));
                                
                                // Format status with color
                                $status_color = '';
                                switch ($application['status']) {
                                    case 'pending':
                                        $status_color = '#f0ad4e';
                                        break;
                                    case 'reviewed':
                                        $status_color = '#5bc0de';
                                        break;
                                    case 'interviewed':
                                        $status_color = '#0275d8';
                                        break;
                                    case 'accepted':
                                        $status_color = '#5cb85c';
                                        break;
                                    case 'rejected':
                                        $status_color = '#d9534f';
                                        break;
                                }
                                
                                // Format date
                                $application_date = date('M d, Y', strtotime($application['created_at']));
                                ?>
                                
                                <div class="job-card" style="margin-bottom: 20px; width: 100%;">
                                    <div class="job-card-header">
                                        <div class="company-logo"><?php echo substr($application['company_name'], 0, 1); ?></div>
                                        <div>
                                            <h3 class="job-card-title"><?php echo $application['title']; ?></h3>
                                            <div class="company-name"><?php echo $application['company_name']; ?></div>
                                        </div>
                                    </div>
                                    <div class="job-card-body">
                                        <div class="job-info">
                                            <div class="job-info-item">
                                                <span class="icon">📍</span> <?php echo $application['location']; ?>
                                            </div>
                                            <div class="job-info-item">
                                                <span class="icon">💼</span> <?php echo $job_type_formatted; ?>
                                            </div>
                                            <div class="job-info-item">
                                                <span class="icon">📅</span> Applied on <?php echo $application_date; ?>
                                            </div>
                                        </div>
                                        <div style="margin-top: 15px;">
                                            <strong>Status:</strong> 
                                            <span style="display: inline-block; padding: 5px 10px; background-color: <?php echo $status_color; ?>; color: white; border-radius: 4px; font-size: 14px;">
                                                <?php echo ucfirst($application['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="job-card-footer">
                                        <a href="index.php?page=job-details&id=<?php echo $application['job_id']; ?>" class="btn">View Job</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>You haven't applied to any jobs yet.</p>
                        <a href="index.php?page=jobs" class="btn mt-3">Browse Jobs</a>
                    <?php endif; ?>
                </div>
                
                <!-- Saved Jobs Tab -->
                <div id="saved-jobs" class="profile-content-item" style="display: none;">
                    <h3 class="profile-section-title">Saved Jobs</h3>
                    
                    <?php if (count($saved_jobs) > 0): ?>
                        <div class="job-list" style="display: block;">
                            <?php foreach ($saved_jobs as $job): ?>
                                <?php
                                // Format job type
                                $job_type_formatted = ucwords(str_replace('_', ' ', $job['job_type']));
                                
                                // Format date
                                $saved_date = date('M d, Y', strtotime($job['created_at']));
                                ?>
                                
                                <div class="job-card" style="margin-bottom: 20px; width: 100%;">
                                    <div class="job-card-header">
                                        <div class="company-logo"><?php echo substr($job['company_name'], 0, 1); ?></div>
                                        <div>
                                            <h3 class="job-card-title"><?php echo $job['title']; ?></h3>
                                            <div class="company-name"><?php echo $job['company_name']; ?></div>
                                        </div>
                                    </div>
                                    <div class="job-card-body">
                                        <div class="job-info">
                                            <div class="job-info-item">
                                                <span class="icon">📍</span> <?php echo $job['location']; ?>
                                            </div>
                                            <div class="job-info-item">
                                                <span class="icon">💼</span> <?php echo $job_type_formatted; ?>
                                            </div>
                                            <div class="job-info-item">
                                                <span class="icon">📅</span> Saved on <?php echo $saved_date; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="job-card-footer">
                                        <a href="index.php?page=job-details&id=<?php echo $job['job_id']; ?>" class="btn">View Job</a>
                                        <form action="index.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                            <button type="submit" name="unsave_job" class="btn btn-danger">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>You haven't saved any jobs yet.</p>
                        <a href="index.php?page=jobs" class="btn mt-3">Browse Jobs</a>
                    <?php endif; ?>
                </div>
            <?php elseif ($user_type === 'employer'): ?>
                <!-- Posted Jobs Tab -->
                <div id="posted-jobs" class="profile-content-item" style="display: none;">
                    <h3 class="profile-section-title">Posted Jobs</h3>
                    
                    <div class="text-right mb-3">
                        <a href="index.php?page=post-job" class="btn">Post a New Job</a>
                    </div>
                    
                    <?php if (count($jobs) > 0): ?>
                        <div class="job-list" style="display: block;">
                            <?php foreach ($jobs as $job): ?>
                                <?php
                                // Format job type
                                $job_type_formatted = ucwords(str_replace('_', ' ', $job['job_type']));
                                
                                // Format date
                                $posted_date = date('M d, Y', strtotime($job['created_at']));
                                
                                // Format status
                                $status_badge = $job['is_active'] ? 
                                    '<span style="display: inline-block; padding: 5px 10px; background-color: #5cb85c; color: white; border-radius: 4px; font-size: 14px;">Active</span>' : 
                                    '<span style="display: inline-block; padding: 5px 10px; background-color: #d9534f; color: white; border-radius: 4px; font-size: 14px;">Inactive</span>';
                                ?>
                                
                                <div class="job-card" style="margin-bottom: 20px; width: 100%;">
                                    <div class="job-card-header">
                                        <div>
                                            <h3 class="job-card-title"><?php echo $job['title']; ?></h3>
                                            <div class="company-name"><?php echo $job['category_name']; ?></div>
                                        </div>
                                    </div>
                                    <div class="job-card-body">
                                        <div class="job-info">
                                            <div class="job-info-item">
                                                <span class="icon">📍</span> <?php echo $job['location']; ?>
                                            </div>
                                            <div class="job-info-item">
                                                <span class="icon">💼</span> <?php echo $job_type_formatted; ?>
                                            </div>
                                            <div class="job-info-item">
                                                <span class="icon">📅</span> Posted on <?php echo $posted_date; ?>
                                            </div>
                                            <div class="job-info-item">
                                                <span class="icon">👥</span> <?php echo $job['application_count']; ?> Applications
                                            </div>
                                        </div>
                                        <div style="margin-top: 15px;">
                                            <strong>Status:</strong> <?php echo $status_badge; ?>
                                        </div>
                                    </div>
                                    <div class="job-card-footer">
                                        <a href="index.php?page=job-details&id=<?php echo $job['job_id']; ?>" class="btn">View Details</a>
                                        <a href="index.php?page=job-applications&id=<?php echo $job['job_id']; ?>" class="btn btn-secondary">View Applications</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>You haven't posted any jobs yet.</p>
                        <a href="index.php?page=post-job" class="btn mt-3">Post Your First Job</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
EOT;
file_put_contents('pages/profile.php', $profile_page);

// Create post-job page
$post_job_page = <<<'EOT'
<section class="section">
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Post a New Job</h2>
            
            <?php if (isset($job_error)): ?>
                <div class="alert alert-danger"><?php echo $job_error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($job_posted)): ?>
                <div class="alert alert-success">Job posted successfully!</div>
            <?php endif; ?>
            
            <form action="index.php?page=post-job" method="POST">
                <div class="form-group">
                    <label for="title" class="form-label">Job Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="category_id" class="form-label">Job Category</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php
                                $categories_sql = "SELECT * FROM job_categories ORDER BY category_name";
                                $categories = getRows($categories_sql);
                                
                                foreach ($categories as $category) {
                                    echo '<option value="' . $category['category_id'] . '">' . $category['category_name'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label for="job_type" class="form-label">Job Type</label>
                            <select id="job_type" name="job_type" class="form-control" required>
                                <option value="">Select Job Type</option>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="internship">Internship</option>
                                <option value="remote">Remote</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" id="location" name="location" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="salary_min" class="form-label">Minimum Salary (optional)</label>
                            <input type="number" id="salary_min" name="salary_min" 
