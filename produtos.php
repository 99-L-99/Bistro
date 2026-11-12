<?php
declare(strict_types=1);
require_once __DIR__.'/config/database.php';
function e($v): string { return htmlspecialchars((string)($v ?? ''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function brl($v): string { return 'R$ '.number_format((float)$v,2,',','.'); }
$erro='';$sucesso='';$editando=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $acao=(string)($_POST['acao']??'');
 try{
  if($acao==='salvar'){
   $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT)?:null;$nome=trim((string)($_POST['nome']??''));$desc=trim((string)($_POST['descricao']??''));$cat=trim((string)($_POST['categoria']??''));$preco=filter_var(str_replace(',','.',trim((string)($_POST['preco']??''))),FILTER_VALIDATE_FLOAT);$ativo=isset($_POST['ativo'])?1:0;
   if($nome===''||strlen($nome)>120)$erro='Informe o nome do produto (até 120 caracteres).';
   elseif($cat===''||strlen($cat)>60)$erro='Informe uma categoria (até 60 caracteres).';
   elseif($preco===false||$preco<0||$preco>99999999)$erro='Informe um preço válido.';
   else{if($id){$s=$pdo->prepare('UPDATE produtos SET nome=?,descricao=?,categoria=?,preco=?,ativo=? WHERE id=?');$s->execute([$nome,$desc!==''?$desc:null,$cat,$preco,$ativo,$id]);$sucesso='Produto atualizado.';}else{$s=$pdo->prepare('INSERT INTO produtos(nome,descricao,categoria,preco,ativo) VALUES(?,?,?,?,?)');$s->execute([$nome,$desc!==''?$desc:null,$cat,$preco,$ativo]);$sucesso='Produto cadastrado.';}}
  }elseif($acao==='alternar'){$id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);if($id){$s=$pdo->prepare('UPDATE produtos SET ativo=IF(ativo=1,0,1) WHERE id=?');$s->execute([$id]);}$sucesso='Disponibilidade atualizada.';}
 }catch(Throwable $ex){error_log('Erro produtos: '.$ex->getMessage());$erro='Não foi possível salvar a alteração.';}
}
if(isset($_GET['editar'])){$id=filter_var($_GET['editar'],FILTER_VALIDATE_INT);if($id){$s=$pdo->prepare('SELECT * FROM produtos WHERE id=?');$s->execute([$id]);$editando=$s->fetch()?:null;}}
$produtos=$pdo->query('SELECT * FROM produtos ORDER BY categoria,nome')->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Produtos | Bistrô Sabor da Vila</title><link rel="stylesheet" href="assets/style.css"></head><body><header class="topbar"><div class="wrap topbar-inner"><a class="brand" href="index.php"><span class="brand-icon">SV</span><span>Bistrô <b>Sabor da Vila</b><small>PAINEL DE COMANDAS</small></span></a><nav><a href="index.php">Comandas</a><a class="nav-active" href="produtos.php">Produtos</a></nav></div></header>
<main class="wrap"><section class="hero"><div><span class="eyebrow">CARDÁPIO E PREÇOS</span><h1>Cadastro de produtos</h1><p>Adicione pratos, altere valores ou retire produtos da lista de pedidos.</p></div><div class="hero-mark">♨</div></section>
<?php if($erro):?><div class="alert error"><?=e($erro)?></div><?php endif;?><?php if($sucesso):?><div class="alert success"><?=e($sucesso)?></div><?php endif;?>
<section class="product-admin-grid"><div class="panel"><div class="panel-heading"><div><span class="step"><?=$editando?'EDITAR PRODUTO':'NOVO CADASTRO'?></span><h2><?=$editando?'Editar produto':'Adicionar produto'?></h2></div></div>
<form method="post" class="admin-form"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($editando['id']??0)?>">
<label>Nome do produto<input name="nome" required maxlength="120" value="<?=e($editando['nome']??'')?>" placeholder="Ex.: Lasanha à bolonhesa"></label>
<label>Categoria<input name="categoria" required maxlength="60" value="<?=e($editando['categoria']??'Pratos feitos')?>" placeholder="Ex.: Pratos feitos"></label>
<label>Preço (R$)<input name="preco" type="number" required min="0" max="99999999" step="0.01" value="<?=e($editando['preco']??'')?>" placeholder="0.00"></label>
<label>Descrição <span class="muted">(opcional)</span><textarea name="descricao" rows="3" maxlength="255" placeholder="Descrição breve"><?=e($editando['descricao']??'')?></textarea></label>
<label class="check-line"><input type="checkbox" name="ativo" value="1" <?=!isset($editando['ativo'])||(int)$editando['ativo']===1?'checked':''?>> Produto disponível para novas comandas</label>
<button class="button primary full" type="submit"><?=$editando?'Salvar alterações':'Cadastrar produto'?></button><?php if($editando):?><a class="cancel-link" href="produtos.php">Cancelar edição</a><?php endif;?></form></div>
<div class="panel inventory"><div class="section-heading"><div><span class="step">CARDÁPIO</span><h2>Produtos cadastrados</h2></div><span class="count"><?=count($produtos)?> itens</span></div>
<?php if(!$produtos):?><div class="empty">Nenhum produto cadastrado.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php foreach($produtos as $p):?><tr><td><strong><?=e($p['nome'])?></strong><?php if($p['descricao']):?><small class="desc-cell"><?=e($p['descricao'])?></small><?php endif;?></td><td><?=e($p['categoria'])?></td><td class="price-cell"><?=brl($p['preco'])?></td><td><span class="status <?=$p['ativo']?'status-done':'status-off'?>"><?=$p['ativo']?'Ativo':'Inativo'?></span></td><td><div class="table-actions"><a class="small-link" href="produtos.php?editar=<?=(int)$p['id']?>">Editar</a><form method="post" onsubmit="return confirm('Alterar disponibilidade deste produto?')"><input type="hidden" name="acao" value="alternar"><input type="hidden" name="id" value="<?=(int)$p['id']?>"><button class="text-button" type="submit"><?=$p['ativo']?'Desativar':'Ativar'?></button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section>
<footer>Projeto didático • UC2 — Práticas DevOps em Projetos Web <span><a href="index.php">Voltar às comandas</a></span></footer></main></body></html>
