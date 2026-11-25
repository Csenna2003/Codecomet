<?php
declare(strict_types=1);

/**
 * Distribui as vagas das licenças da escola entre os alunos sem acesso ao curso.
 * Trava a linha da licença para dois cadastros simultâneos não ocuparem a mesma vaga.
 */
function aplicar_licencas(string $orgId): void
{
    foreach (todos('SELECT * FROM licencas WHERE org_id = ?', [$orgId]) as $lic) {
        if ($lic['validade'] && strtotime($lic['validade']) < time()) continue;
        transacao(function () use ($lic, $orgId) {
            q('SELECT id FROM licencas WHERE id = ? FOR UPDATE', [$lic['id']]);
            $usadas = (int) valor("SELECT COUNT(*) FROM acessos WHERE licenca_id = ? AND status = 'ATIVO'", [$lic['id']]);
            $alunos = todos("SELECT usuario_id FROM membros WHERE org_id = ? AND papel = 'ALUNO' ORDER BY criado_em", [$orgId]);
            foreach ($alunos as $al) {
                if ($usadas >= (int) $lic['vagas_total']) break;
                $uid = $al['usuario_id'];
                if (acesso_ativo($uid, $lic['curso_id'])) continue;
                $atual = um('SELECT id FROM acessos WHERE usuario_id = ? AND curso_id = ?', [$uid, $lic['curso_id']]);
                if ($atual) {
                    q(
                        "UPDATE acessos SET org_id = ?, licenca_id = ?, pedido_id = NULL, origem = 'LICENCA_ESCOLA',
                         status = 'ATIVO', inicio = ?, fim = ? WHERE id = ?",
                        [$orgId, $lic['id'], agora(), $lic['validade'], $atual['id']]
                    );
                } else {
                    q(
                        "INSERT INTO acessos (id, usuario_id, curso_id, org_id, licenca_id, origem, status, inicio, fim)
                         VALUES (?,?,?,?,?,'LICENCA_ESCOLA','ATIVO',?,?)",
                        [novo_id(), $uid, $lic['curso_id'], $orgId, $lic['id'], agora(), $lic['validade']]
                    );
                }
                $usadas++;
            }
        });
    }
}

/** Vincula o usuário à escola (e à turma do convite). Retorna ['erro' => ...] ou ['org' => nome]. */
function usar_convite(string $uid, string $codigoBruto): array
{
    $codigo = strtoupper(trim($codigoBruto));
    $c = um(
        'SELECT c.*, o.nome AS org_nome, o.ativa FROM convites c JOIN organizacoes o ON o.id = c.org_id WHERE c.codigo = ?',
        [$codigo]
    );
    if (!$c || !$c['ativo'] || !$c['ativa']) return ['erro' => 'Código inválido.'];

    $jaMembro = um('SELECT id FROM membros WHERE usuario_id = ? AND org_id = ?', [$uid, $c['org_id']]);
    if (!$jaMembro) {
        // Incremento condicional: não ultrapassa o limite mesmo com usos simultâneos.
        $ok = q(
            'UPDATE convites SET usos = usos + 1 WHERE id = ? AND (max_usos IS NULL OR usos < max_usos)',
            [$c['id']]
        )->rowCount();
        if (!$ok) return ['erro' => 'Este código já atingiu o limite de usos.'];
        q('INSERT INTO membros (id, usuario_id, org_id, papel, criado_em) VALUES (?,?,?,?,?)',
            [novo_id(), $uid, $c['org_id'], $c['papel'], agora()]);
    }
    if ($c['turma_id'] && $c['papel'] === 'ALUNO') {
        q('INSERT IGNORE INTO turma_alunos (turma_id, usuario_id, entrou_em) VALUES (?,?,?)', [$c['turma_id'], $uid, agora()]);
    }
    aplicar_licencas($c['org_id']);
    return ['org' => $c['org_nome']];
}

/** Ao sair da escola: perde os acessos da licença, mantém progresso e certificados. */
function remover_da_escola(string $uid, string $orgId): void
{
    transacao(function () use ($uid, $orgId) {
        q("UPDATE acessos SET status = 'REVOGADO' WHERE usuario_id = ? AND org_id = ? AND origem = 'LICENCA_ESCOLA'", [$uid, $orgId]);
        q('DELETE ta FROM turma_alunos ta JOIN turmas t ON t.id = ta.turma_id WHERE ta.usuario_id = ? AND t.org_id = ?', [$uid, $orgId]);
        q('UPDATE turmas SET professor_id = NULL WHERE org_id = ? AND professor_id = ?', [$orgId, $uid]);
        q('DELETE FROM membros WHERE usuario_id = ? AND org_id = ?', [$uid, $orgId]);
    });
    aplicar_licencas($orgId); // a vaga liberada vai para quem estava na fila
}

/**
 * Quais entregas o usuário pode corrigir, como condição SQL sobre o alias "e":
 * admin: todas; coordenador/admin da escola: todas da escola; professor: só alunos das turmas dele.
 * Retorna null para quem não corrige nada.
 */
function filtro_entregas(array $u): ?array
{
    if (eh_admin($u)) return ['1=1', []];
    $partes = [];
    $p = [];
    $geridas = array_keys(orgs_geridas($u));
    if ($geridas) {
        $partes[] = 'e.org_id IN (' . marcas($geridas) . ')';
        $p = array_merge($p, $geridas);
    }
    foreach (array_keys(orgs_professor($u)) as $org) {
        $partes[] = '(e.org_id = ? AND EXISTS (SELECT 1 FROM turma_alunos ta JOIN turmas t ON t.id = ta.turma_id
                      WHERE ta.usuario_id = e.usuario_id AND t.org_id = ? AND t.professor_id = ?))';
        array_push($p, $org, $org, $u['id']);
    }
    return $partes ? ['(' . implode(' OR ', $partes) . ')', $p] : null;
}

/** Marca o pedido como pago e libera o curso. Pode ser chamado mais de uma vez. */
function confirmar_pedido(string $pedidoId, ?string $referencia = null): ?array
{
    return transacao(function () use ($pedidoId, $referencia) {
        $p = um('SELECT * FROM pedidos WHERE id = ? FOR UPDATE', [$pedidoId]);
        if (!$p || $p['status'] === 'CANCELADO') return null;
        if ($p['status'] === 'PENDENTE') {
            q("UPDATE pedidos SET status = 'PAGO', pago_em = ?, referencia = ? WHERE id = ?", [agora(), $referencia, $pedidoId]);
        }
        $atual = um('SELECT id FROM acessos WHERE usuario_id = ? AND curso_id = ?', [$p['usuario_id'], $p['curso_id']]);
        if ($atual) {
            q(
                "UPDATE acessos SET origem = 'COMPRA', status = 'ATIVO', pedido_id = ?, org_id = NULL, licenca_id = NULL,
                 fim = NULL, inicio = ? WHERE id = ?",
                [$pedidoId, agora(), $atual['id']]
            );
        } else {
            q(
                "INSERT INTO acessos (id, usuario_id, curso_id, pedido_id, origem, status, inicio) VALUES (?,?,?,?,'COMPRA','ATIVO',?)",
                [novo_id(), $p['usuario_id'], $p['curso_id'], $pedidoId, agora()]
            );
        }
        return $p;
    });
}
