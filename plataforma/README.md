# Codecomet — plataforma de cursos de Robótica e Programação

Área de membros/LMS com o que uma área de membros comum não tem: **simulador dentro da aula
(Tinkercad e Wokwi), entrega de projetos com correção por rubrica, trilhas com liberação
sequencial, certificado verificável e separação por escolas.**

Stack: Next.js 15 (App Router, Server Actions) · PostgreSQL · Prisma · TypeScript.

**Para colocar no ar, siga o [PUBLICAR.md](PUBLICAR.md).**

## Como rodar (no seu computador)

```bash
cd plataforma
cp .env.example .env          # ajuste DATABASE_URL, WEBHOOK_SECRET e os dados do Pix
npm install
npm run db:migrate            # cria as tabelas
npm run db:seed               # dados de demonstração (opcional)
npm run dev                   # http://localhost:3000
# produção: npm run build && npm run start   (o start aplica as migrações antes de subir)
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

## Senhas e primeiro acesso

- **Primeiro administrador:** a primeira conta criada com o e-mail definido em `ADMIN_EMAIL` vira administradora.
- **Limite de login:** 5 erros em 15 minutos bloqueiam novas tentativas para aquele e-mail.
- **Esqueci a senha:** não há envio de e-mail. O coordenador (em `/escola`, botão "Nova senha") ou o admin
  (em `/admin`) gera um link de uso único, válido por 24 horas, e o entrega ao usuário.

## Pagamento (Pix)

Com `PAGAMENTO_MODO=pix`, a compra de um curso funciona assim:

1. O aluno clica em **Comprar curso** e vê o QR Code e o **Pix copia e cola**, já com o valor do curso.
2. Depois de pagar, clica em **Já paguei**.
3. Você abre **Administração → Conferir pagamentos** (`/admin/pedidos`), confere no extrato do banco se o Pix
   chegou e clica em **Confirmar recebimento**. O curso é liberado na hora.

O Pix cai direto na sua conta, sem intermediário e sem taxa. Configure no `.env`:

```
PIX_CHAVE="..."     # exatamente como cadastrada no banco
PIX_NOME="..."      # titular da conta
PIX_CIDADE="..."    # cidade do titular
```

O identificador de cada Pix é o número do pedido; ele aparece no extrato de quem recebe e na tela de conferência.

**Antes de vender, faça um teste real:** crie um curso de R$ 1,00, pague pelo aplicativo do seu banco e confira
se o nome do recebedor e o valor aparecem certos.

**Confirmação automática:** a rota `POST /api/webhooks/pagamento` (cabeçalho `x-webhook-secret`, corpo
`{ "pedidoId": "...", "status": "PAGO" }`) já libera o curso sem conferência manual. Para usá-la é preciso
contratar um serviço de cobrança (Asaas, Mercado Pago, Efí…) e adaptar a rota ao formato dele — ainda não feito.

Cursos gratuitos liberam na hora. `PAGAMENTO_MODO=simulado` troca o Pix por um botão de teste (só desenvolvimento).

## Ainda não incluído

- Confirmação automática do Pix e pagamento por cartão (ver acima)
- E-mails (boas-vindas, aviso de correção, recuperação de senha por e-mail)
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
