<?php
// Menu compartilhado: edite aqui para alterar em todas as páginas.
$links = ['index.php' => 'Comandas', 'reservas.php' => 'Reservas', 'produtos.php' => 'Produtos'];
$paginaAtual = basename($_SERVER['SCRIPT_NAME']);
?>
<style>
.nav-reservas{background:#d7a46d!important;color:#17271f!important;font-weight:700}
.reservas-flutuante{position:fixed;right:18px;bottom:18px;z-index:9999;background:#d7a46d;color:#17271f;padding:13px 18px;border-radius:999px;font:700 .9rem system-ui,sans-serif;text-decoration:none;box-shadow:0 6px 20px rgba(0,0,0,.3)}
.reservas-flutuante:hover{background:#e6b988}
</style>
<nav><?php foreach ($links as $arquivo => $rotulo):
    $classes = [];
    if ($arquivo === $paginaAtual) $classes[] = 'nav-active';
    if ($arquivo === 'reservas.php') $classes[] = 'nav-reservas';
?><a <?= $classes ? 'class="'.implode(' ', $classes).'" ' : '' ?>href="<?= $arquivo ?>"><?= $rotulo ?></a><?php endforeach; ?></nav>
<?php if ($paginaAtual !== 'reservas.php'): ?><a class="reservas-flutuante" href="reservas.php">🍽 Reservas</a><?php endif; ?>