# Como publicar no Railway

Resultado: a plataforma no ar em `cursos.seusite.com.br`, com o site atual continuando onde está.

Os nomes dos botões do Railway estão em inglês e podem mudar um pouco com o tempo.

## 1. Criar o projeto

1. Entre em railway.com e clique em **New Project → Deploy from GitHub repo**.
2. Autorize o acesso ao GitHub e escolha o repositório **Codecomet**.
3. O primeiro deploy vai falhar — é esperado, faltam os passos 2 e 3.

## 2. Apontar para a pasta certa

Clique no serviço criado → aba **Settings**:

- **Source → Root Directory:** `plataforma`
- **Source → Branch:** a branch onde está a plataforma (`main` depois que o código for mesclado).

Não é preciso preencher comandos de build ou de start: o Railway usa os do projeto, e as tabelas do banco
são criadas sozinhas a cada publicação.

## 3. Criar o banco de dados

Na tela do projeto: **Create → Database → Add PostgreSQL**.

## 4. Configurar as variáveis

Clique no serviço da plataforma → aba **Variables** → adicione:

| Variável | Valor |
|---|---|
| `DATABASE_URL` | `${{Postgres.DATABASE_URL}}` (digite exatamente assim; o Railway preenche sozinho) |
| `PAGAMENTO_MODO` | `pix` |
| `PIX_CHAVE` | sua chave Pix, exatamente como cadastrada no banco |
| `PIX_NOME` | nome do titular da conta (até 25 letras) |
| `PIX_CIDADE` | cidade do titular (até 15 letras) |
| `ADMIN_EMAIL` | o e-mail que você vai usar para administrar a plataforma |

Formato da chave Pix: CPF/CNPJ só com números, telefone como `+5511999998888`, e-mail ou chave aleatória.

Depois de salvar, clique em **Deploy**.

## 5. Abrir o site e criar sua conta de administrador

1. No serviço → **Settings → Networking → Generate Domain**. O Railway cria um endereço provisório.
2. Abra esse endereço, clique em **Criar conta** e cadastre-se **com o mesmo e-mail de `ADMIN_EMAIL`**.
   Faça isso logo após publicar: a primeira conta com esse e-mail vira a administradora.
3. O menu **Administração** deve aparecer. É ali que você cria cursos, escolas e licenças.

O banco começa vazio. Não rode o `db:seed` em produção: ele cria contas de demonstração com senha conhecida.

## 6. Usar o seu domínio

1. No Railway: **Settings → Networking → Custom Domain** → digite `cursos.seusite.com.br`.
   O Railway mostra os registros de DNS a criar (um `CNAME` e, às vezes, um `TXT` de verificação).
2. Na HostGator: **cPanel → Zone Editor (Editor de Zona DNS)** → no seu domínio, **Adicionar registro**:
   - Tipo `CNAME`, nome `cursos`, destino = o valor que o Railway mostrou.
   - Se o Railway pediu um `TXT`, crie também, com o nome e o valor indicados.
3. Aguarde de alguns minutos a algumas horas. Quando o Railway marcar o domínio como ativo, o endereço
   já funciona com cadeado (HTTPS).
4. No site atual, crie um botão "Área do aluno" apontando para `https://cursos.seusite.com.br`.

Se o domínio foi registrado em outra empresa (Registro.br, por exemplo) e o DNS não é gerenciado na HostGator,
o registro `CNAME` é criado no painel dessa outra empresa.

## 7. Antes de vender

- [ ] Crie um curso de R$ 1,00, compre com outra conta e pague o Pix pelo seu banco. Confira o nome do
      recebedor, o valor e se o pedido aparece em **Administração → Conferir pagamentos**.
- [ ] Abra uma aula de simulador e veja se o Tinkercad/Wokwi aparece dentro da página.
- [ ] Verifique no Railway como funcionam os backups do banco no seu plano.
