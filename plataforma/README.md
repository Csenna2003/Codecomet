# Codecomet — plataforma de cursos de Robótica e Programação

Área de membros/LMS com o que uma área de membros comum não tem: **simulador dentro da aula
(Tinkercad e Wokwi), entrega de projetos com correção por rubrica, trilhas com liberação
sequencial, certificado verificável e separação por escolas.**

Stack: Next.js 15 (App Router, Server Actions) · PostgreSQL · Prisma · TypeScript.

## Como rodar

```bash
cd plataforma
cp .env.example .env          # ajuste DATABASE_URL e WEBHOOK_SECRET
npm install
npm run db:migrate            # cria as tabelas
npm run db:seed               # dados de demonstração (opcional)
npm run dev                   # http://localhost:3000
```

Contas criadas pelo seed (senha em `SEED_SENHA`, padrão `codecomet123`):

| E-mail | Papel |
|---|---|
| `admin@codecomet.dev` | Administrador da plataforma |
| `coordenacao@escola-demo.dev` | Coordenadora da Escola Demonstração |
| `professor@escola-demo.dev` | Professor do 7º ano A |
| `ana@escola-demo.dev` | Aluna com acesso pela licença da escola |
| `igor@exemplo.dev` | Aluno individual, sem cursos |

Código de convite da turma de demonstração: `DEMO7ANO`.

**Troque essas senhas (ou não rode o seed) em produção.**

## O que cada perfil faz

| Perfil | Onde | O que faz |
|---|---|---|
| Aluno | `/painel`, `/cursos/…`, `/aprender/…` | Compra ou entra com o código da escola, assiste às aulas em sequência, usa o simulador, responde quiz, envia projetos, recebe o certificado |
| Professor | `/professor` | Corrige as entregas **dos alunos das turmas dele** com rubrica, nota e comentário |
| Coordenador / admin da escola | `/escola` | Turmas, códigos de convite, vagas da licença, progresso dos alunos; corrige qualquer entrega da escola |
| Admin da plataforma | `/admin` | Cria cursos/módulos/aulas, escolas e licenças; vê todas as escolas e corrige alunos de venda direta |

## Regras de negócio

- **Liberação sequencial:** uma aula abre quando todas as obrigatórias anteriores estão concluídas.
- **Conclusão por tipo:** vídeo/leitura/simulador pelo botão; quiz com 70% de acerto; exercício ao enviar
  (o aluno não fica travado esperando a correção).
- **Certificado:** emitido automaticamente com 100% das aulas obrigatórias concluídas **e** todos os
  exercícios obrigatórios aprovados. Página pública em `/certificado/CODIGO`, que também serve para imprimir em PDF.
- **Escolas:** o admin cria a escola e uma licença (N vagas de um curso). A escola distribui um código
  por turma; ao usá-lo o aluno entra na turma e ocupa uma vaga. Sem vaga, fica na fila e recebe a próxima que liberar.
- **Saída da escola:** o aluno perde o acesso da licença, mas mantém progresso e certificados.
- **Menores de 18 anos:** o cadastro exige nome, e-mail e autorização do responsável.
- **Simulador:** o autor cola o link do projeto (`tinkercad.com/things/…` ou `wokwi.com/projects/…`) e a aula
  mostra o projeto embutido. Nas entregas, o aluno envia o link do projeto dele e pode colar o código —
  a cópia do código fica guardada como prova mesmo se o projeto for apagado no simulador.

## Pagamento

`Pedido` fica `PENDENTE` até a confirmação. Cursos gratuitos liberam na hora.

- `PAGAMENTO_MODO=simulado`: mostra o botão "Simular pagamento aprovado" (desenvolvimento).
- Produção: o gateway chama `POST /api/webhooks/pagamento` com o cabeçalho `x-webhook-secret` e o corpo
  `{ "pedidoId": "...", "status": "PAGO" }`. **A integração com um gateway específico (geração da cobrança
  Pix/cartão e validação da assinatura dele) ainda precisa ser feita** em `src/app/api/webhooks/pagamento/route.ts`
  e na ação `matricular`.

## Ainda não incluído

- Gateway de pagamento real (ver acima)
- E-mails (boas-vindas, aviso de correção, recuperação de senha)
- Limite de tentativas de login
- Upload de arquivos nas entregas (hoje: link do simulador, código ou link de vídeo)
- Correção automática de simulações
- Edição de aulas já criadas e reordenação (hoje: criar e excluir)

## Estrutura

```
prisma/schema.prisma      modelo de dados (comentado)
prisma/seed.ts            dados de demonstração
src/lib/auth.ts           sessão e papéis
src/lib/curso.ts          acesso, liberação sequencial, certificado
src/lib/escola.ts         convites, vagas das licenças, saída da escola
src/lib/revisao.ts        quem pode corrigir quais entregas (isolamento entre escolas)
src/lib/simulador.ts      links do Tinkercad/Wokwi/YouTube -> embed
src/app/acoes/            todas as gravações (Server Actions), com checagem de permissão
src/app/                  telas
```
