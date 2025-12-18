<?php

include 'database_connect.php';

//search logic
$input = $_GET['search'] ?? '';
$input = trim($input);
$is_searching = !empty($input);
$reult = null;

if($is_searching){
    $search = "SELECT * FROM studentgeneraldata WHERE admission LIKE ? AND accessoriesstatus = 'uncleared' ";
    $search = $connecting_to_the_database->prepare($search);
    $search->bind_param("s", $input);
    $search->execute();
    $result = $search->get_result();
    $search->close();
}


//pagination
//COLLECTINg OF THE NUMBER OF STUDENTS WITH CLEARED FEES
$result_per_page = 1;
$current_page = $_GET['page'] ?? 1;


$stmt = "SELECT COUNT(*) FROM studentgeneraldata WHERE accessoriesstatus='uncleared' ";
$stmt = $connecting_to_the_database->prepare($stmt);
$stmt->execute();
$stmt = $stmt->get_result();
$total_records = $stmt->fetch_array()[0];
$stmt->close();

    
$total_pages = ceil($total_records / $result_per_page);
if($current_page > $total_pages && $total_pages > 0){
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $result_per_page;
if($offset < 0){
    $offset = 0;
}

$sql = "SELECT * FROM studentgeneraldata WHERE accessoriesstatus='uncleared' LIMIT $offset, $result_per_page";
$sql = $connecting_to_the_database->prepare($sql);
$sql->execute();
$main_result = $sql->get_result();
$sql->close();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>accessories department cleared</title>
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
        <h2><span class="material-symbols-outlined">dashboard</span>uncleared accessories department</h2>
        <div class="links">
             <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
             <a href="admindash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="search">
        <form action="#" method="get">
            <label for="">
            search
            <input type="search" name="search" placeholder="Type the admission of student">
            <input type="submit" value="search">
            </label>
        </form>
    </div>
    <div class="searchoutput">
        <h2>search output</h2>
        <table class="table">
            <th>name</th>
            <th>admission</th>
            <th>year</th>
            <th>unpaid accessories </th>
            <th>accessories status</th>
            <?php
            if($is_searching && $result && $result->num_rows > 0){
             while($info = $result->fetch_assoc()){?>
            <tr>
                <td><?php echo "{$info['username']}"?></td>
                <td><?php echo "{$info['admission']}"?></td>
                <td><?php echo "{$info['year']}"?></td>
                <td><?php echo "{$info['unpaidaccessories']}"?></td>
                <td><?php echo "{$info['accessoriesstatus']}"?></td>
            </tr>
            <?php 
            }
            }elseif (empty($info)) {
                // Message when a search was attempted but returned zero results
                echo '<tr><td colspan="11">No student found matching the admission: ' . htmlspecialchars($input) .' who has uncleared accessories fees'. '</td></tr>';
            } else {
                 // Message when no search has been initiated yet
                 echo '<tr><td colspan="11">Enter an admission number to search.</td></tr>';
            }
            ?>
        </table>
    </div>
    <div class="details">
        <h2>students with uncleared accessories fees</h2>
        <table>
            <th>name</th>
            <th>admission</th>
            <th>year</th>
            <th>unpaid accessories</th>
            <th>accessories status</th>

            <?php while($info = $main_result->fetch_assoc()) { ?>

            <tr>
                <td><?php echo "{$info['username']}"?></td>
                <td><?php echo "{$info['admission']}"?></td>
                <td><?php echo "{$info['year']}"?></td>
                <td><?php echo "{$info['unpaidaccessories']}"?></td>
                <td><?php echo "{$info['accessoriesstatus']}"?></td>
            </tr>

            <?php } ?>
            
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            // --- Previous Page Link Logic ---
            $prev_page = $current_page - 1;
            $prev_class = $current_page <= 1 ? 'disabled' : '';
            ?>
            <a href="?page=<?php echo $prev_page; ?>" class="<?php echo $prev_class; ?>">Previous</a>

            <?php
            // --- Page Number Links Logic ---
            for ($i = 1; $i <= $total_pages; $i++) {
                $active_class = $i == $current_page ? 'active' : '';
                ?>
                <a href="?page=<?php echo $i ; ?>" class="<?php echo $active_class; ?>"><?php echo $i; ?></a>
                <?php
            }

            // --- Next Page Link Logic ---
            $next_page = $current_page + 1;
            $next_class = $current_page >= $total_pages ? 'disabled' : '';
            ?>
            <a href="?page=<?php echo $next_page; ?>" class="<?php echo $next_class; ?>">Next</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>