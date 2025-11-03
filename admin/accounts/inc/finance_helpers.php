<?php

if (!function_exists('acc_is_admin')) {
    function acc_is_admin(): bool {
        return isAuthenticated() && hasRole(['super_admin']);
    }
}

if (!function_exists('finance_require_admin')) {
    function finance_require_admin() {
        if (!acc_is_admin()) { redirect('../login.php'); }
    }
}

if (!function_exists('money')) {
    function money($v): string { return number_format((float)$v, 2); }
}

if (!function_exists('bn_digits')) {
    function bn_digits($input): string {
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        return str_replace($en, $bn, (string)$input);
    }
}

if (!function_exists('get_classes')) {
    function get_classes(PDO $pdo): array {
        return $pdo->query("SELECT id, name FROM classes ORDER BY numeric_value ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('get_sections_by_class')) {
    function get_sections_by_class(PDO $pdo, int $class_id): array {
        $st = $pdo->prepare("SELECT id, name FROM sections WHERE class_id = ? ORDER BY name ASC");
        $st->execute([$class_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('finance_flash')) {
    function finance_flash(string $type, string $msg) {
        $_SESSION['finance_flash'] = ['type'=>$type,'msg'=>$msg];
    }
}

if (!function_exists('finance_flash_show')) {
    function finance_flash_show() {
        if (!empty($_SESSION['finance_flash'])) {
            $f = $_SESSION['finance_flash']; unset($_SESSION['finance_flash']);
            $cls = $f['type']==='success' ? 'alert-success' : ($f['type']==='error' ? 'alert-danger' : 'alert-info');
            echo '<div class="alert '.$cls.' alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button>'.htmlspecialchars($f['msg']).'</div>';
        }
    }
}
?>
