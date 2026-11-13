<?php
declare(strict_types=1);
require_once __DIR__.'/config/database.php';
date_default_timezone_set('America/Sao_Paulo');

const DURACAO_RESERVA_MIN = 120;   // tempo que a mesa fica ocupada por reserva
const ANTECEDENCIA_MAX_DIAS = 90;  // limite para agendar no futuro

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function telefone(string $d): string {
    if (strlen($d) === 11) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    return $d;
}
function dataValida(?string $d): ?DateTimeImmutable {
    if (!$d) return null;
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return ($dt && $dt->format('Y-m-d') === $d) ? $dt : null;
}

$agora = new DateTimeImmutable('now');
$erro = ''; $sucesso = '';
$form = ['nome' => '', 'telefone' => '', 'data' => $agora->format('Y-m-d'), 'hora' => '', 'pessoas' => '2', 'mesa' => '0', 'observacao' => ''];

$mesas = $pdo->query('SELECT id,numero,lugares FROM mesas WHERE ativa=1 ORDER BY numero')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($acao === 'reservar') {
            $form = [
                'nome' => trim((string)($_POST['nome'] ?? '')),
                'telefone' => trim((string)($_POST['telefone'] ?? '')),
                'data' => trim((string)($_POST['data'] ?? '')),
                'hora' => trim((string)($_POST['hora'] ?? '')),
                'pessoas' => trim((string)($_POST['pessoas'] ?? '')),
                'mesa' => trim((string)($_POST['mesa'] ?? '0')),
                'observacao' => trim((string)($_POST['observacao'] ?? '')),
            ];
            $digitos = preg_replace('/\D+/', '', $form['telefone']) ?? '';
            $pessoas = filter_var($form['pessoas'], FILTER_VALIDATE_INT);
            $mesaId = filter_var($form['mesa'], FILTER_VALIDATE_INT);
            $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $form['data'].' '.$form['hora']);
            if ($inicio && $inicio->format('Y-m-d H:i') !== $form['data'].' '.$form['hora']) $inicio = false;

            if ($form['nome'] === '' || mb_strlen($form['nome']) > 120) $erro = 'Informe o nome do cliente (até 120 caracteres).';
            elseif (strlen($digitos) < 10 || strlen($digitos) > 13) $erro = 'Informe um telefone válido com DDD.';
            elseif (!$inicio) $erro = 'Informe uma data e um horário válidos.';
            elseif ($inicio < $agora) $erro = 'Não é possível reservar em um horário que já passou.';
            elseif ($inicio > $agora->modify('+'.ANTECEDENCIA_MAX_DIAS.' days')) $erro = 'Só é possível reservar com até '.ANTECEDENCIA_MAX_DIAS.' dias de antecedência.';
            elseif (!$pessoas || $pessoas < 1 || $pessoas > 99) $erro = 'Informe a quantidade de pessoas (1 a 99).';
            elseif ($mesaId === false || $mesaId < 0) $erro = 'Mesa inválida.';
            elseif (mb_strlen($form['observacao']) > 500) $erro = 'A observação pode ter até 500 caracteres.';
            else {
                // Candidatas: a mesa escolhida ou, no modo automático, as menores que comportam o grupo.
                $candidatas = [];
                foreach ($mesas as $m) {
                    if ((int)$m['lugares'] < $pessoas) continue;
                    if ($mesaId === 0 || (int)$m['id'] === $mesaId) $candidatas[] = $m;
                }
                usort($candidatas, fn($a, $b) => [(int)$a['lugares'], (int)$a['numero']] <=> [(int)$b['lugares'], (int)$b['numero']]);

                if (!$candidatas) {
                    $erro = $mesaId === 0
                        ? 'Nenhuma mesa comporta essa quantidade de pessoas.'
                        : 'A mesa escolhida não existe, está inativa ou não comporta essa quantidade de pessoas.';
                } else {
                    $de = $inicio->modify('-'.DURACAO_RESERVA_MIN.' minutes')->format('Y-m-d H:i:s');
                    $ate = $inicio->modify('+'.DURACAO_RESERVA_MIN.' minutes')->format('Y-m-d H:i:s');
                    $reservadaEm = null;
                    foreach ($candidatas as $m) {
                        $pdo->beginTransaction();
                        // Trava a linha da mesa para impedir duas reservas simultâneas no mesmo horário.
                        $pdo->prepare('SELECT id FROM mesas WHERE id=? FOR UPDATE')->execute([$m['id']]);
                        $s = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE mesa_id=? AND status='confirmada' AND data_hora>? AND data_hora<?");
                        $s->execute([$m['id'], $de, $ate]);
                        if ((int)$s->fetchColumn() === 0) {
                            $s = $pdo->prepare('INSERT INTO reservas (mesa_id,nome_cliente,telefone,pessoas,data_hora,observacao) VALUES (?,?,?,?,?,?)');
                            $s->execute([$m['id'], $form['nome'], $digitos, $pessoas, $inicio->format('Y-m-d H:i:s'), $form['observacao'] !== '' ? $form['observacao'] : null]);
                            $pdo->commit();
                            $reservadaEm = $m;
                            break;
                        }
                        $pdo->rollBack();
                    }
                    if ($reservadaEm) {
                        header('Location: reservas.php?data='.$inicio->format('Y-m-d').'&ok=criada&mesa='.(int)$reservadaEm['numero']);
                        exit;
                    }
                    $erro = $mesaId === 0
                        ? 'Não há mesa livre para esse horário. Tente outro horário ou outro dia.'
                        : 'A mesa escolhida já está reservada nesse horário (cada reserva ocupa '.(DURACAO_RESERVA_MIN / 60).' h). Escolha outro horário ou use "Automática".';
                }
            }
        } elseif ($acao === 'cancelar' || $acao === 'concluir') {
            $id = filter_var($_POST['reserva_id'] ?? '', FILTER_VALIDATE_INT);
            $novo = $acao === 'cancelar' ? 'cancelada' : 'concluida';
            if ($id) {
                $s = $pdo->prepare("UPDATE reservas SET status=? WHERE id=? AND status='confirmada'");
                $s->execute([$novo, $id]);
            }
            $data = dataValida((string)($_POST['data'] ?? '')) ? (string)$_POST['data'] : $agora->format('Y-m-d');
            header('Location: reservas.php?data='.$data.'&ok='.($acao === 'cancelar' ? 'cancelada' : 'concluida'));
            exit;
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Erro nas reservas: '.$ex->getMessage());
        $erro = 'Não foi possível concluir a operação. Confira o banco e se as tabelas de reservas foram criadas (database.sql).';
    }
}

$ok = (string)($_GET['ok'] ?? '');
if ($ok === 'criada') $sucesso = 'Reserva confirmada na mesa '.(int)($_GET['mesa'] ?? 0).'.';
elseif ($ok === 'cancelada') $sucesso = 'Reserva cancelada.';
elseif ($ok === 'concluida') $sucesso = 'Reserva marcada como concluída.';

$diaSel = dataValida((string)($_GET['data'] ?? '')) ?? $agora->setTime(0, 0);
$diaStr = $diaSel->format('Y-m-d');

try {
    $s = $pdo->prepare('SELECT r.*,m.numero mesa_numero,m.lugares FROM reservas r JOIN mesas m ON m.id=r.mesa_id WHERE DATE(r.data_hora)=? ORDER BY r.data_hora,m.numero');
    $s->execute([$diaStr]);
    $reservas = $s->fetchAll();
} catch (Throwable $ex) {
    error_log('Erro ao listar reservas: '.$ex->getMessage());
    $reservas = [];
    if (!$erro) $erro = 'Não foi possível carregar as reservas. Importe as tabelas de reservas (database.sql).';
}
$confirmadas = count(array_filter($reservas, fn($r) => $r['status'] === 'confirmada'));
$rotulo = ['confirmada' => ['status-open', '● Confirmada'], 'concluida' => ['status-done', '✓ Concluída'], 'cancelada' => ['status-off', '✕ Cancelada']];
$meses = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
$tituloDia = ucfirst($dias[(int)$diaSel->format('w')]).', '.$diaSel->format('d').' '.$meses[(int)$diaSel->format('n')].' '.$diaSel->format('Y');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reservas | Bistrô Sabor da Vila</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header class="topbar"><div class="wrap topbar-inner"><a class="brand" href="index.php"><span class="brand-icon">SV</span><span>Bistrô <b>Sabor da Vila</b><small>PAINEL DE COMANDAS</small></span></a><?php require __DIR__.'/menu.php'; ?></div></header>
<main class="wrap"><section class="hero"><div><span class="eyebrow">SALÃO</span><h1>Reserva de mesas</h1><p>Agende mesas por data e horário. Cada reserva ocupa a mesa por <?=DURACAO_RESERVA_MIN / 60?> horas.</p></div><div class="hero-mark">🍽</div></section>
<?php if ($erro): ?><div class="alert error"><?=e($erro)?></div><?php endif; ?><?php if ($sucesso): ?><div class="alert success"><?=e($sucesso)?></div><?php endif; ?>
<section class="layout">
<div class="panel"><div class="panel-heading"><div><span class="step">01 / NOVA RESERVA</span><h2>Agendar mesa</h2></div><span class="pill">Salão</span></div>
<?php if (!$mesas): ?><div class="empty">Nenhuma mesa ativa cadastrada. Importe database.sql para criar as mesas de exemplo.</div><?php else: ?>
<form method="post" class="admin-form"><input type="hidden" name="acao" value="reservar">
<label>Nome do cliente<input name="nome" required maxlength="120" value="<?=e($form['nome'])?>" placeholder="Ex.: Maria Souza"></label>
<label>Telefone (com DDD)<input name="telefone" type="tel" required maxlength="20" value="<?=e($form['telefone'])?>" placeholder="(49) 99999-9999"></label>
<div class="form-grid">
<label>Data<input name="data" type="date" required min="<?=e($agora->format('Y-m-d'))?>" max="<?=e($agora->modify('+'.ANTECEDENCIA_MAX_DIAS.' days')->format('Y-m-d'))?>" value="<?=e($form['data'])?>"></label>
<label>Horário<input name="hora" type="time" required step="900" value="<?=e($form['hora'])?>"></label>
</div>
<div class="form-grid">
<label>Pessoas<input name="pessoas" type="number" required min="1" max="99" value="<?=e($form['pessoas'])?>"></label>
<label>Mesa<select name="mesa"><option value="0">Automática</option><?php foreach ($mesas as $m): ?><option value="<?=(int)$m['id']?>" <?=(string)$m['id'] === $form['mesa'] ? 'selected' : ''?>>Mesa <?=(int)$m['numero']?> (<?=(int)$m['lugares']?> lugares)</option><?php endforeach; ?></select></label>
</div>
<label>Observação <span class="muted">(opcional)</span><textarea name="observacao" rows="3" maxlength="500" placeholder="Ex.: aniversário, cadeirinha de bebê..."><?=e($form['observacao'])?></textarea></label>
<button class="button primary full" type="submit">＋ Confirmar reserva</button></form>
<p class="hint">No modo "Automática", o sistema escolhe a menor mesa livre que comporta o grupo.</p><?php endif; ?></div>

<div class="orders-column">
<div class="section-heading"><div><span class="step">02 / AGENDA</span><h2><?=e($tituloDia)?></h2></div><span class="count"><?=$confirmadas?> confirmada(s)</span></div>
<form method="get" class="date-nav">
<a class="button secondary" href="reservas.php?data=<?=e($diaSel->modify('-1 day')->format('Y-m-d'))?>">← Anterior</a>
<input type="date" name="data" value="<?=e($diaStr)?>" aria-label="Escolher data">
<button class="button secondary" type="submit">Ver</button>
<a class="button secondary" href="reservas.php?data=<?=e($agora->format('Y-m-d'))?>">Hoje</a>
<a class="button secondary" href="reservas.php?data=<?=e($diaSel->modify('+1 day')->format('Y-m-d'))?>">Próximo →</a>
</form>
<?php if (!$reservas): ?><div class="panel empty large">Nenhuma reserva para este dia.<br><span>Use o formulário para agendar uma mesa.</span></div>
<?php else: foreach ($reservas as $r): [$cls, $txt] = $rotulo[$r['status']] ?? ['status-off', $r['status']]; ?>
<article class="order-card <?=$r['status'] === 'concluida' ? 'order-done' : ''?> <?=$r['status'] === 'cancelada' ? 'order-cancelled' : ''?>">
<div class="order-top"><div><span class="order-number"><?=e(date('H:i', strtotime($r['data_hora'])))?> · MESA <?=(int)$r['mesa_numero']?></span><div class="order-date"><?=e($r['nome_cliente'])?> · <?=(int)$r['pessoas']?> pessoa(s) · mesa de <?=(int)$r['lugares']?> lugares</div></div><span class="status <?=$cls?>"><?=e($txt)?></span></div>
<div class="reservation-meta"><span>📞 <?=e(telefone((string)$r['telefone']))?></span></div>
<?php if ($r['observacao']): ?><div class="observation"><b>Observação:</b> <?=nl2br(e($r['observacao']))?></div><?php endif; ?>
<?php if ($r['status'] === 'confirmada'): ?><div class="order-actions">
<form method="post"><input type="hidden" name="acao" value="concluir"><input type="hidden" name="reserva_id" value="<?=(int)$r['id']?>"><input type="hidden" name="data" value="<?=e($diaStr)?>"><button class="button done-button" type="submit">✓ Cliente atendido</button></form>
<form method="post" onsubmit="return confirm('Cancelar a reserva de <?=e(addslashes($r['nome_cliente']))?>?')"><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="reserva_id" value="<?=(int)$r['id']?>"><input type="hidden" name="data" value="<?=e($diaStr)?>"><button class="text-button danger" type="submit">Cancelar reserva</button></form>
</div><?php endif; ?></article>
<?php endforeach; endif; ?>
</div></section>
<footer>Projeto didático • UC2 — Práticas DevOps em Projetos Web <span>PHP + MySQL</span></footer></main></body></html>