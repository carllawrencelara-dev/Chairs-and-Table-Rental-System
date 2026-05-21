<script src="assets/js/main.js"></script>
<?php if (isset($includeCharts) && $includeCharts): ?>
<script>
<?php if (isset($monthlyRevenue)): ?>
const monthlyData = <?php echo json_encode($monthlyRevenue); ?>;
<?php endif; ?>
<?php if (isset($weeklyRevenue)): ?>
const weeklyData = <?php echo json_encode($weeklyRevenue); ?>;
<?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>