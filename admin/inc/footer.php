<!-- Main Footer -->
<footer class="main-footer">
    <strong>কপিরাইট &copy; ২০২৫ <a target="_blank" href="https://batighorbd.com/">বাতিঘর কম্পিউটার’স</a>.</strong>
    সর্বস্বত্ব সংরক্ষিত।
    <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> 1.0.0
    </div>
</footer>
<?php
// Fallback enforcement for title suffix in case header is not included
$__instNameF = '';
try {
        if (isset($pdo) && $pdo instanceof PDO) {
                $row = $pdo->query("SELECT name FROM school_info LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $__instNameF = $row['name'] ?? '';
        }
} catch (Exception $e) { $__instNameF = $__instNameF ?: ''; }
?>
<script>
(function(){
    try{
        var site = <?php echo json_encode($__instNameF, JSON_UNESCAPED_UNICODE); ?> || '';
        if(!site) return;
        var t = document.title || '';
        if(!t) return;
        var suffix = ' - ' + site;
        if(t.slice(-suffix.length) !== suffix){ document.title = t + suffix; }
    }catch(e){}
})();
</script>