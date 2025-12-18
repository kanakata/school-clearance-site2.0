<?php

include 'database_connect.php';

// Configuration
$records_per_page = 1; 

// Get current page, default to 1
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Search Logic Start
// This section handles user search input and executes the dedicated search query ---

// Initialize search variables
$search_term = trim($_GET['search'] ?? '');
$is_searching = !empty($search_term);

// The only search variable needed for binding
$like_search_term = "%" . $search_term . "%";

// 1. Dedicated Search Output Query (Non-Paginated)
$search_output_result = null;

if ($is_searching) {
    // Build SEARCH OUTPUT SQL for all matching records (no LIMIT/OFFSET)
    $search_output_sql = "SELECT * FROM " . 'studentgeneraldata' . " WHERE admission LIKE ?" . " ORDER BY admission ASC";
    $search_output_stmt = $connecting_to_the_database->prepare($search_output_sql);
    
    // Bind search term (s)
    $search_output_stmt->bind_param("s", $like_search_term);
    $search_output_stmt->execute();
    $search_output_result = $search_output_stmt->get_result();
    $search_output_stmt->close();
}

// Search Logic End
// =====================================================

// Main Display and Pagination Logic Start
// This section calculates total records and executes the paginated query for the main table ---

// 1. Get Total Records (Independent of Search for the main paginated table)

// The count query must NOT include the WHERE clause when calculating for the main, paginated display.
// The original code was unnecessarily including the search in the count, which would only count records
// matching the search for the *main* table, which is not correct for a full student list.

$count_sql = "SELECT COUNT(*) FROM " . 'studentgeneraldata'; // Count ALL records
$total_records_stmt = $connecting_to_the_database->prepare($count_sql);

// No bind_param needed as there is no WHERE clause.

$total_records_stmt->execute();
$total_records = $total_records_stmt->get_result()->fetch_row()[0];
$total_records_stmt->close(); 

$total_pages = ceil($total_records / $records_per_page);

// 2. Handle invalid page number access
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages; 
}
// Calculate the final OFFSET after page adjustment
$offset = ($current_page - 1) * $records_per_page;
if ($offset < 0) $offset = 0;


// 3. Execute Main Paginated Data Query
// This query is for the 'All Students General Data' table. It should NOT include the search WHERE clause.

$main_data_sql = "SELECT * FROM " . 'studentgeneraldata' . " ORDER BY admission ASC LIMIT ?, ?";
$main_data_stmt = $connecting_to_the_database->prepare($main_data_sql);

// Bind parameters: LIMIT values (i, i)
$main_data_stmt->bind_param("ii", $offset, $records_per_page);

$main_data_stmt->execute();
$main_result = $main_data_stmt->get_result();

// 🖼️ Main Display and Pagination Logic End
// -----------------------------------------------------------------------------------

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student Details</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        /* --- Simple Styling for Pagination Links (You should move this to style.css) --- */
        .pagination {
            display: flex;
            justify-content: center;
            padding: 20px 0;
            margin-top: 15px;
        }
        .pagination a {
            color: #333;
            padding: 8px 16px;
            text-decoration: none;
            transition: background-color .3s;
            border: 1px solid #ddd;
            margin: 0 4px;
            border-radius: 4px;
        }
        .pagination a:hover:not(.active) { background-color: #f2f2f2; }
        .pagination a.active {
            background-color: #301CA0; /* Active page color */
            color: white;
            border: 1px solid #301CA0;
        }
        .pagination a.disabled {
            pointer-events: none;
            cursor: default;
            color: #ccc;
            border-color: #eee;
        }
        .details table th, .searchoutput table th {
            background-color: #f0f0f0;
            color: #333;
        }
    </style>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">dashboard</span>view all student details</h2>
        <div class="links">
             <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
             <a href="admindash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    
    <div class="search">
        <form action="" method="get">
            <label for="search">
            search
            <input type="search" name="search" placeholder="Type student admission" value="<?php echo htmlspecialchars($search_term); ?>">
            <input type="submit" value="search">
            </label>
        </form>
    </div>

    <div class="searchoutput">
        <h2>search output</h2>
        <table class="table">
            <tr>
                <th>name</th><th>admission</th><th>year</th><th>fee balance</th>
                <th>books lost</th><th>boarding items damaged</th><th>unpaid accessories</th>
                <th>games items lost</th><th>lab fee</th><th>clearance status</th>
                <th>student profile picture</th>
            </tr>
            <?php
            // PHP to display the search results
            if ($is_searching && $search_output_result && $search_output_result->num_rows > 0) {
                while($row = $search_output_result->fetch_assoc()) {
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['admission']); ?></td>
                <td><?php echo htmlspecialchars($row['year']); ?></td>
                <td><?php echo htmlspecialchars($row['feebalance']); ?></td>
                <td><?php echo htmlspecialchars($row['bookslost']); ?></td>
                <td><?php echo htmlspecialchars($row['boardingitemsdamaged']); ?></td>
                <td><?php echo htmlspecialchars($row['unpaidaccessories']); ?></td>
                <td><?php echo htmlspecialchars($row['gamesitemslost']); ?></td>
                <td><?php echo htmlspecialchars($row['labfee']); ?></td>
                <td><?php echo htmlspecialchars($row['clearancestatus']); ?></td>
                <td><img src="profile pictures/<?php echo htmlspecialchars($row['userprofilepic']); ?>" alt="Profile Picture" class="viewprofilepicture"></td>
            </tr>
            <?php
                }
            } elseif ($is_searching) {
                // Message when a search was attempted but returned zero results
                echo '<tr><td colspan="11">No student found matching the admission: ' . htmlspecialchars($search_term) . '</td></tr>';
            } else {
                 // Message when no search has been initiated yet
                 echo '<tr><td colspan="11">Enter an admission number to search.</td></tr>';
            }
            ?>
        </table>
    </div>

    <div class="details">
        <h2>all students general data (<?php echo $total_records; ?> Total)</h2>
        <table>
            <tr>
                <th>username</th><th>admission</th><th>year</th><th>fee balance</th>
                <th>books lost</th><th>boarding items damaged</th><th>unpaid accessories</th>
                <th>games items lost</th><th>lab fee </th><th>clearance status </th>
                <th>update</th><th>student profile picture </th>
            </tr>

            <?php
            // PHP to loop through the paginated results (main_result)
            if ($main_result && $main_result->num_rows > 0) {
                while($row = $main_result->fetch_assoc()) {
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['admission']); ?></td>
                <td><?php echo htmlspecialchars($row['year']); ?></td>
                <td><?php echo htmlspecialchars($row['feebalance']); ?></td>
                <td><?php echo htmlspecialchars($row['bookslost']); ?></td>
                <td><?php echo htmlspecialchars($row['boardingitemsdamaged']); ?></td>
                <td><?php echo htmlspecialchars($row['unpaidaccessories']); ?></td>
                <td><?php echo htmlspecialchars($row['gamesitemslost']); ?></td>
                <td><?php echo htmlspecialchars($row['labfee']); ?></td>
                <td><?php echo htmlspecialchars($row['clearancestatus']); ?></td>
                <td><a href="update_student.php?admission=<?php echo htmlspecialchars($row['admission']); ?>">Update</a></td>
                <td><img src="profile pictures/<?php echo htmlspecialchars($row['userprofilepic']); ?>" alt="Profile Picture" class="viewprofilepicture"></td>
            </tr>
            <?php
                }
            } else {
                echo '<tr><td colspan="12">No student records found.</td></tr>';
            }

            // Clean up the database connection after all data is used
            $main_data_stmt->close();
            if (isset($connecting_to_the_database)) {
                $connecting_to_the_database->close(); // Close the main connection
            }
            ?>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            // Create the search query parameter IN-LINE for the URL
            $search_url_param = $is_searching ? "&search=" . urlencode($search_term) : '';

            // --- Previous Page Link Logic ---
            $prev_page = $current_page - 1;
            $prev_class = $current_page <= 1 ? 'disabled' : '';
            ?>
            <a href="?page=<?php echo $prev_page . $search_url_param; ?>" class="<?php echo $prev_class; ?>">Previous</a>

            <?php
            // --- Page Number Links Logic ---
            for ($i = 1; $i <= $total_pages; $i++) {
                $active_class = $i === $current_page ? 'active' : '';
                ?>
                <a href="?page=<?php echo $i . $search_url_param; ?>" class="<?php echo $active_class; ?>"><?php echo $i; ?></a>
                <?php
            }

            // --- Next Page Link Logic ---
            $next_page = $current_page + 1;
            $next_class = $current_page >= $total_pages ? 'disabled' : '';
            ?>
            <a href="?page=<?php echo $next_page . $search_url_param; ?>" class="<?php echo $next_class; ?>">Next</a>
        </div>
        <?php endif; ?>
        
    </div>
</body>
</html>