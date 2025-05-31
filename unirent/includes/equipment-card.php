<div class="equipment-card">
    <div class="equipment-image">
        <?php if (!empty($item['image']) && file_exists($item['image'])): ?>
            <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
        <?php else: ?>
            <img src="assets/images/placeholder.jpg" alt="<?php echo $item['name']; ?>">
        <?php endif; ?>
        <div class="equipment-badge">
            <?php if ($item['user_type'] == 2): ?>
                <span class="badge university">University</span>
            <?php else: ?>
                <span class="badge student">Student</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="equipment-info">
        <h3><a href="equipment.php?id=<?php echo $item['id']; ?>"><?php echo $item['name']; ?></a></h3>
        <p class="equipment-category"><?php echo $item['category_name']; ?></p>
        <div class="equipment-meta">
            <div class="equipment-price">
                <i class="fas fa-tag"></i>
                <span><?php echo ($item['daily_rate'] > 0) ? '$' . number_format($item['daily_rate'], 2) . '/day' : 'Free'; ?></span>
            </div>
            <div class="equipment-owner">
                <i class="fas fa-user"></i>
                <span><?php echo $item['first_name'] . ' ' . substr($item['last_name'], 0, 1) . '.'; ?></span>
            </div>
        </div>
        <div class="equipment-actions">
            <a href="equipment.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
            <a href="rent.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-secondary">Rent Now</a>
        </div>
    </div>
</div>