<?php
declare(strict_types=1);
require_once __DIR__.'/config/database.php';
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v): string { return 'R$ '.number_format((float)$v,2,',','.'); }
$erro=''; $sucesso='';
$produtos=$pdo->query("SELECT id,nome,categoria,preco FROM produtos WHERE ativo=1 ORDER BY categoria,nome")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
 $acao=(string)($_POST['acao']??'');
 try {
  if ($acao==='criar_comanda') {
   $observacao=trim((string)($_POST['observacao']??'')); $itens=[];
   foreach (($_POST['quantidade']??[]) as $pid=>$qraw) {
    $pid=filter_var($pid,FILTER_VALIDATE_INT); $q=filter_var($qraw,FILTER_VALIDATE_INT);
    if (!$pid || !$q || $q<1 || $q>99) continue;
    $s=$pdo->prepare('SELECT id,nome,preco FROM produtos WHERE id=? AND ativo=1'); $s->execute([$pid]); $p=$s->fetch();
    if ($p) $itens[]=['p'=>$p,'q'=>$q];
   }
   if (!$itens) $erro='Selecione ao menos um produto e informe uma quantidade maior que zero.';
   else {
    $pdo->beginTransaction();
    $s=$pdo->prepare('INSERT INTO comandas (observacao) VALUES (?)'); $s->execute([$observacao!==''?$observacao:null]);
    $id=(int)$pdo->lastInsertId();
    $s=$pdo->prepare('UPDATE comandas SET numero=? WHERE id=?'); $s->execute([$id,$id]);
    $si=$pdo->prepare('INSERT INTO itens_comanda (comanda_id,produto_id,nome_produto,quantidade,preco_unitario) VALUES (?,?,?,?,?)');
    foreach($itens as $it) $si->execute([$id,$it['p']['id'],$it['p']['nome'],$it['q'],$it['p']['preco']]);
    $pdo->commit(); header('Location: index.php?criada='.$id); exit;
   }
  } elseif ($acao==='entregar') {
   $id=filter_var($_POST['comanda_id']??'',FILTER_VALIDATE_INT);
   if($id){$s=$pdo->prepare("UPDATE comandas SET status='entregue',data_entrega=NOW() WHERE id=? AND status='aberta'");$s->execute([$id]);}
   header('Location: index.php?entregue=1'); exit;
  } elseif ($acao==='observacao') {
   $id=filter_var($_POST['comanda_id']??'',FILTER_VALIDATE_INT); $obs=trim((string)($_POST['observacao']??''));
   if($id){$s=$pdo->prepare("UPDATE comandas SET observacao=? WHERE id=? AND status='aberta'");$s->execute([$obs!==''?$obs:null,$id]);}
   header('Location: index.php?obs=1'); exit;
  }
 } catch(Throwable $ex) {
  if($pdo->inTransaction()) $pdo->rollBack();
  error_log('Erro nas comandas: '.$ex->getMessage()); $erro='Não foi possível concluir a operação. Confira banco e configuração.';
 }
}
if(isset($_GET['criada'])) $sucesso='Comanda #'.(int)$_GET['criada'].' registrada com sucesso.';
if(isset($_GET['entregue'])) $sucesso='Comanda marcada como entregue.';
if(isset($_GET['obs'])) $sucesso='Observação atualizada.';
$comandas=$pdo->query("SELECT c.*,COALESCE(SUM(i.quantidade*i.preco_unitario),0) total,COALESCE(SUM(i.quantidade),0) unidades FROM comandas c LEFT JOIN itens_comanda i ON i.comanda_id=c.id GROUP BY c.id ORDER BY c.id DESC LIMIT 30")->fetchAll();
$itensPor=[];
if($comandas){$ids=array_map(fn($c)=>(int)$c['id'],$comandas);$ph=implode(',',array_fill(0,count($ids),'?'));$s=$pdo->prepare("SELECT * FROM itens_comanda WHERE comanda_id IN ($ph) ORDER BY id");$s->execute($ids);foreach($s->fetchAll() as $i)$itensPor[$i['comanda_id']][]=$i;}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comandas | Bistrô Sabor da Vila</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header class="topbar"><div class="wrap topbar-inner"><a class="brand" href="index.php"><span class="brand-icon">SV</span><span>Bistrô <b>Sabor da Vila</b><small>PAINEL DE COMANDAS</small></span></a><nav><a class="nav-active" href="index.php">Comandas</a><a href="produtos.php">Produtos</a></nav></div></header>
<main class="wrap"><section class="hero"><div><span class="eyebrow">OPERAÇÃO DA COZINHA</span><h1>Comandas do restaurante</h1><p>Registre pedidos, acompanhe o preparo e marque pedidos entregues.</p></div><div class="hero-mark">☕</div></section>
<?php if($erro):?><div class="alert error"><?=e($erro)?></div><?php endif;?><?php if($sucesso):?><div class="alert success"><?=e($sucesso)?></div><?php endif;?>
<section class="layout"><div class="panel"><div class="panel-heading"><div><span class="step">01 / NOVO PEDIDO</span><h2>Abrir comanda</h2></div><span class="pill">Cozinha</span></div>
<form method="post"><input type="hidden" name="acao" value="criar_comanda">
<?php if(!$produtos):?><div class="empty">Nenhum produto ativo. Cadastre produtos no painel Produtos.</div><?php else:?><div class="product-list"><?php $cat='';foreach($produtos as $p):if($cat!==$p['categoria']):$cat=$p['categoria'];?><div class="category-label"><?=e($cat)?></div><?php endif;?><label class="product-row"><span class="product-info"><strong><?=e($p['nome'])?></strong><small><?=brl($p['preco'])?></small></span><span class="quantity"><span>Qtd.</span><input type="number" name="quantidade[<?=(int)$p['id']?>]" min="0" max="99" value="0" aria-label="Quantidade de <?=e($p['nome'])?>"></span></label><?php endforeach;?></div>
<label class="field-label" for="obs">Observação geral da cozinha</label><textarea id="obs" name="observacao" rows="3" maxlength="2000" placeholder="Ex.: sem cebola, molho à parte..."></textarea><button class="button primary full" type="submit">＋ Registrar comanda</button><?php endif;?></form><p class="hint">O preço do item é salvo na comanda, preservando o valor histórico mesmo que o cardápio mude.</p></div>
<div class="orders-column"><div class="section-heading"><div><span class="step">02 / ACOMPANHAMENTO</span><h2>Últimas comandas</h2></div><span class="count"><?=count($comandas)?> / 30</span></div>
<?php if(!$comandas):?><div class="panel empty large">Nenhuma comanda registrada ainda.<br><span>Use o formulário para criar o primeiro pedido.</span></div><?php else:foreach($comandas as $c):?><article class="order-card <?=$c['status']==='entregue'?'order-done':''?>"><div class="order-top"><div><span class="order-number">COMANDA #<?=(int)$c['numero']?></span><div class="order-date"><?=e(date('d/m/Y H:i',strtotime($c['data_abertura'])))?></div></div><span class="status <?=$c['status']==='entregue'?'status-done':'status-open'?>"><?=$c['status']==='entregue'?'✓ Entregue':'● Em preparo'?></span></div>
<div class="order-items"><?php foreach(($itensPor[$c['id']]??[]) as $i):?><div class="order-item"><span><b><?=(int)$i['quantidade']?>×</b> <?=e($i['nome_produto'])?></span><strong><?=brl($i['quantidade']*$i['preco_unitario'])?></strong></div><?php endforeach;?></div>
<div class="order-total"><span><?=(int)$c['unidades']?> item(ns)</span><strong><?=brl($c['total'])?></strong></div>
<?php if($c['observacao']):?><div class="observation"><b>Observação:</b> <?=nl2br(e($c['observacao']))?></div><?php endif;?>
<?php if($c['status']==='entregue'):?><div class="delivered-note">Entregue em <?=e(date('d/m/Y H:i',strtotime($c['data_entrega'])))?></div><?php else:?><div class="order-actions"><form method="post" onsubmit="return confirm('Marcar comanda #<?=(int)$c['numero']?> como entregue?')"><input type="hidden" name="acao" value="entregar"><input type="hidden" name="comanda_id" value="<?=(int)$c['id']?>"><button class="button done-button" type="submit">✓ Marcar entregue</button></form><details><summary>Editar observação</summary><form method="post" class="edit-note"><input type="hidden" name="acao" value="observacao"><input type="hidden" name="comanda_id" value="<?=(int)$c['id']?>"><textarea name="observacao" rows="2" maxlength="2000"><?=e($c['observacao'])?></textarea><button class="button secondary" type="submit">Salvar observação</button></form></details></div><?php endif;?></article><?php endforeach;endif;?></div></section>
<footer>Projeto didático • UC2 — Práticas DevOps em Projetos Web <span>PHP + MySQL</span></footer></main></body></html>
