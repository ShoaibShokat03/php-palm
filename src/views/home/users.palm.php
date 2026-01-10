<?php

/** @phpstan-ignore-file */

?>

<div class="content-section">
    <h1>Users PHP Palm</h1>

    <div class="section">
        <?php if (empty($users)) { ?>
            <p>No users found</p>
        <?php } else { ?>
            <?php $srNo=0;?>
            <?php foreach ($users as $user) {
                $srNo++;
                echo "<div class='user'>";
                echo "<h4>" . $srNo . ". " . $user['name'] . "</h4>";
                echo "<p>" . $user['email'] . "</p>";
                echo "</div>";
            } ?>
        <?php } ?>
    </div>

</div>