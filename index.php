<?php
session_start();

// Database Connection
$conn = mysqli_connect("localhost", "root", "", "login");

// Connection Status & Error Handling
$dbConnected = false;
$message = "";
$messageType = "";

if ($conn) {
    $dbConnected = true;
} else {
    die("<div style='color:red; font-size:16px; margin:20px;'>❌ Database Connection Failed: " . mysqli_connect_error() . "</div>");
}

// Helper function: Sanitize and validate input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper function: Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Helper function: Validate name contains only letters and spaces
function validateName($name) {
    return preg_match('/^[a-zA-Z\s]+$/', $name);
}

// Helper function: Check if email already exists
function emailExists($conn, $email, $excludeId = null) {
    $email = sanitizeInput($email);
    $query = "SELECT id FROM users WHERE email = ?";
    
    if ($excludeId) {
        $query .= " AND id != ?";
    }
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($excludeId) {
        mysqli_stmt_bind_param($stmt, "si", $email, $excludeId);
    } else {
        mysqli_stmt_bind_param($stmt, "s", $email);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

// INSERT - With validation
if (isset($_POST['save'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    
    if (empty($name) || empty($email)) {
        $message = "❌ All fields are required";
        $messageType = "error";
    } elseif (!validateName($name)) {
        $message = "❌ Name must contain only letters and spaces.";
        $messageType = "error";
    } elseif (!validateEmail($email)) {
        $message = "❌ Invalid email format.";
        $messageType = "error";
    } elseif (emailExists($conn, $email)) {
        $message = "❌ This email already exists. Please use a different email.";
        $messageType = "error";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO users(name, email) VALUES(?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $name, $email);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "✅ User added successfully";
            $messageType = "success";
            header("Location: index.php?success=added");
            exit();
        } else {
            $message = "❌ Error adding user";
            $messageType = "error";
        }
    }
}

// DELETE - Confirmed deletion
if (isset($_GET['delete']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $id = intval($_GET['delete']);
    
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: index.php?success=deleted");
        exit();
    } else {
        $message = "❌ Error deleting user";
        $messageType = "error";
    }
}

// EDIT FETCH - Get user for editing
$editData = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $editData = mysqli_fetch_assoc($result);
}

// UPDATE - With validation
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    
    if (empty($name) || empty($email)) {
        $message = "❌ All fields are required";
        $messageType = "error";
    } elseif (!validateName($name)) {
        $message = "❌ Name must contain only letters and spaces.";
        $messageType = "error";
    } elseif (!validateEmail($email)) {
        $message = "❌ Invalid email format.";
        $messageType = "error";
    } elseif (emailExists($conn, $email, $id)) {
        $message = "❌ This email already exists. Please use a different email.";
        $messageType = "error";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: index.php?success=updated");
            exit();
        } else {
            $message = "❌ Error updating user";
            $messageType = "error";
        }
    }
}

// SEARCH & PAGINATION
$search = sanitizeInput($_GET['search'] ?? '');
$page = intval($_GET['page'] ?? 1);
$itemsPerPage = 5;
$offset = ($page - 1) * $itemsPerPage;

// Build search query
$searchCondition = "";
if (!empty($search)) {
    $search = '%' . $search . '%';
    $searchCondition = " WHERE name LIKE ? OR email LIKE ?";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users" . $searchCondition;
$countStmt = mysqli_prepare($conn, $countQuery);

if (!empty($search)) {
    mysqli_stmt_bind_param($countStmt, "ss", $search, $search);
}

mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $itemsPerPage);

// Get paginated data
$dataQuery = "SELECT * FROM users" . $searchCondition . " ORDER BY id DESC LIMIT ?, ?";
$dataStmt = mysqli_prepare($conn, $dataQuery);

if (!empty($search)) {
    mysqli_stmt_bind_param($dataStmt, "ssii", $search, $search, $offset, $itemsPerPage);
} else {
    mysqli_stmt_bind_param($dataStmt, "ii", $offset, $itemsPerPage);
}

mysqli_stmt_execute($dataStmt);
$users = mysqli_stmt_get_result($dataStmt);

// Handle success messages
if (isset($_GET['success'])) {
    $successMsg = $_GET['success'];
    if ($successMsg === 'added') {
        $message = "✅ User added successfully";
        $messageType = "success";
    } elseif ($successMsg === 'updated') {
        $message = "✅ User updated successfully";
        $messageType = "success";
    } elseif ($successMsg === 'deleted') {
        $message = "✅ User deleted successfully";
        $messageType = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional CRUD System</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #eef2f7;
            color: #1f2937;
            min-height: 100vh;
            padding: 24px;
        }

        .container {
            max-width: 1120px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .header {
            padding: 32px 40px;
            background: #ffffff;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            letter-spacing: -0.03em;
        }

        .header p {
            color: #4b5563;
            font-size: 0.97rem;
            line-height: 1.6;
        }

        .content {
            padding: 30px;
        }

        /* Alert Messages */
        .alert {
            padding: 18px 22px;
            margin-bottom: 24px;
            border-radius: 16px;
            font-weight: 600;
            animation: slideDown 0.3s ease-out;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f5c6cb;
        }

        /* Form Styles */
        .form-section {
            background: #f8fafc;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 32px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .form-section h2 {
            color: #1d4ed8;
            margin-bottom: 22px;
            font-size: 1.25rem;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #111827;
            font-weight: 700;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            font-size: 0.97rem;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
            font-family: inherit;
            background: #ffffff;
        }

        input[type="text"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        input[type="text"]::placeholder,
        input[type="email"]::placeholder {
            color: #6b7280;
        }

        .form-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        button {
            padding: 14px 24px;
            border: none;
            border-radius: 14px;
            font-size: 0.96rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            font-family: inherit;
            min-width: 160px;
        }

        button[type="submit"] {
            background: #2563eb;
            color: white;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
            flex: 1;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            background: #1d4ed8;
        }

        button[type="reset"] {
            background: #f3f4f6;
            color: #111827;
            flex: 1;
        }

        button[type="reset"]:hover {
            background: #e5e7eb;
        }

        /* Search Section */
        .search-section {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-section input {
            flex: 1;
            min-width: 280px;
            padding: 14px 18px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            font-size: 0.96rem;
            background: #ffffff;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .search-section button {
            padding: 14px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .search-section button:hover {
            transform: translateY(-2px);
            background: #1d4ed8;
        }

        .clear-search {
            padding: 14px 24px !important;
            background: #f3f4f6 !important;
            color: #111827 !important;
            border-radius: 14px;
            text-decoration: none;
        }

        .clear-search:hover {
            background: #e5e7eb !important;
        }

        /* Table Styles */
        .table-section {
            margin-top: 30px;
        }

        .table-section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #ffffff;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
            border-radius: 18px;
            overflow: hidden;
        }

        thead {
            background: #f3f4f6;
            color: #111827;
        }

        th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.12em;
            color: #475569;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
            color: #334155;
            font-size: 0.96rem;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .actions a,
        .actions button {
            padding: 10px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background-color: #2563eb;
            color: white;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.16);
        }

        .btn-edit:hover {
            transform: translateY(-2px);
        }

        .btn-delete {
            background-color: #ef4444;
            color: white;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.16);
        }

        .btn-delete:hover {
            transform: translateY(-2px);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
        }

        .pagination a {
            color: #667eea;
            background: white;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
            border-color: #e9ecef;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state svg {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        /* Stats */
        .stats {
            display: flex;
            gap: 18px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: #ffffff;
            color: #111827;
            padding: 24px 26px;
            border-radius: 20px;
            flex: 1;
            min-width: 180px;
            text-align: left;
            border: 1px solid #e5e7eb;
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.05);
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #475569;
            font-size: 0.95rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }

            .content {
                padding: 20px;
            }

            .search-section {
                flex-direction: column;
            }

            .search-section input,
            .search-section button {
                width: 100%;
            }

            .actions {
                flex-direction: column;
            }

            .actions a,
            .actions button {
                width: 100%;
                text-align: center;
            }

            table {
                font-size: 0.9em;
            }

            th, td {
                padding: 10px;
            }
        }

        .form-actions {
            flex-wrap: wrap;
        }

        .form-actions button {
            min-width: 150px;
        }
    </style>
</head>

<body>

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <h1>Professional User Management</h1>
        <p>Secure, polished and easy-to-use contact administration with fast search, edits, and seamless workflow.</p>

    <div class="content">
        
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $totalRecords; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $totalPages; ?></h3>
                <p>Pages</p>
            </div>
        </div>

        <!-- Form Section -->
        <div class="form-section">
            <h2><?php echo $editData ? '📝 Edit User' : '➕ Add New User'; ?></h2>
            
            <form method="POST" id="userForm">
                <input type="hidden" name="id" value="<?php echo $editData['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input 
                        type="text" 
                        id="name"
                        name="name" 
                        placeholder="Enter user's full name" 
                        value="<?php echo sanitizeInput($editData['name'] ?? ''); ?>"
                        required
                        minlength="2"
                        maxlength="100"
                        pattern="[A-Za-z ]+"
                        title="Only letters and spaces are allowed.">
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input 
                        type="email" 
                        id="email"
                        name="email" 
                        placeholder="Enter valid email address" 
                        value="<?php echo sanitizeInput($editData['email'] ?? ''); ?>"
                        required
                        maxlength="100">
                </div>

                <div class="form-actions">
                    <?php if ($editData): ?>
                        <button type="submit" name="update">💾 Update User</button>
                        <a href="index.php" style="padding: 12px 25px; background: #e9ecef; color: #333; text-decoration: none; border-radius: 8px; text-align: center; font-weight: 600;">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="save">✅ Add User</button>
                        <button type="reset">🔄 Reset</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" style="display: flex; gap: 10px; width: 100%; flex-wrap: wrap;">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="🔍 Search by name or email..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="flex: 1; min-width: 250px;">
                <button type="submit">🔍 Search</button>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="clear-search">✕ Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Section -->
        <div class="table-section">
            <h2>👥 Users Directory</h2>
            
            <?php if (mysqli_num_rows($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($users)): ?>
                        <tr>
                            <td><strong><?php echo $row['id']; ?></strong></td>
                            <td><?php echo sanitizeInput($row['name']); ?></td>
                            <td><?php echo sanitizeInput($row['email']); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?edit=<?php echo $row['id']; ?>" class="btn-edit">✏️ Edit</a>
                                    <a href="?delete=<?php echo $row['id']; ?>&confirm=yes" class="btn-delete" onclick="return confirm('⚠️ Are you sure you want to delete this user? This action cannot be undone.');">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">« First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">‹ Previous</a>
                    <?php else: ?>
                        <span class="disabled">« First</span>
                        <span class="disabled">‹ Previous</span>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next ›</a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Last »</a>
                    <?php else: ?>
                        <span class="disabled">Next ›</span>
                        <span class="disabled">Last »</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <h3>📭 No Users Found</h3>
                    <p><?php echo !empty($search) ? "No users match your search. Try a different query." : "Start by adding your first user using the form above."; ?></p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>