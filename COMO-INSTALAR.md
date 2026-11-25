# Como colocar a plataforma de cursos no ar (HostGator, sem contratar nada)

A plataforma roda **na hospedagem que você já tem** (Plano P da HostGator): PHP e MySQL,
que vêm inclusos no plano. Ela fica numa pasta do seu site:

```
https://www.adororobotica.com.br/cursos/      ← ajuste para o seu domínio
```

No seu site, basta um botão ou link **"Área do aluno"** apontando para esse endereço.

Tempo estimado: 15 minutos. Tudo é feito pelo cPanel da HostGator.

---

## 1. Criar o banco de dados (cPanel → "Bancos de dados MySQL")

1. Em **Criar novo banco de dados**, digite `cursos` e clique em **Criar banco de dados**.
   O nome final fica com o prefixo da sua conta, por exemplo `adoro123_cursos`. **Anote o nome completo.**
2. Em **Usuários MySQL → Adicionar novo usuário**, digite `cursos`, crie uma senha forte
   (use o gerador de senha) e clique em **Criar usuário**. **Anote o usuário completo e a senha.**
3. Em **Adicionar usuário ao banco de dados**, escolha o usuário e o banco que você criou,
   clique em **Adicionar** e marque **Todos os privilégios**. Clique em **Fazer alterações**.

## 2. Conferir a versão do PHP (cPanel → "MultiPHP Manager" ou "Selecionar versão do PHP")

Escolha **PHP 8.1 ou mais novo** para o seu domínio. Se já estiver assim, não mexa.

## 3. Enviar os arquivos (cPanel → "Gerenciador de arquivos")

1. Abra a pasta **public_html**.
2. Clique em **Carregar** e envie o arquivo **`cursos.zip`** (está na raiz deste repositório).
3. Volte para **public_html**, clique com o botão direito em `cursos.zip` → **Extract** (Extrair).
   Vai aparecer a pasta **`cursos`**. Depois pode apagar o `cursos.zip`.

Se o seu site é WordPress, tudo bem: a pasta `cursos` convive com ele sem interferir.

## 4. Instalar (no navegador)

1. Abra `https://www.SEUSITE.com.br/cursos/instalar.php` **logo após enviar os arquivos**.
2. **Passo 1:** nome da plataforma, os dados do banco (passo 1 deste guia) e os dados do Pix.
   - Chave Pix exatamente como cadastrada no banco: CPF/CNPJ só com números, telefone como
     `+5511999998888`, e-mail ou chave aleatória.
   - Os dados do Pix podem ficar para depois (veja "Mudar o Pix" abaixo).
3. **Passo 2:** crie sua conta de administrador. Ele pede de novo a senha do banco, para garantir
   que é você. Deixe marcado "Criar um curso de exemplo" para ver tudo funcionando.

Pronto: você cai no painel de **Administração**. O `instalar.php` fica travado depois disso
(pode apagá-lo pelo Gerenciador de arquivos, se quiser).

## 5. Colocar o link no seu site

Crie um botão ou item de menu **"Área do aluno"** com o link `https://www.SEUSITE.com.br/cursos/`.

---

## Antes de divulgar

- [ ] **Cadeado (HTTPS):** abra o endereço com `https://`. Se não abrir, no cPanel procure
      **SSL/TLS Status** e rode o **AutoSSL** (gratuito na HostGator).
- [ ] **Pix de verdade:** crie um curso de R$ 1,00, compre com outra conta, pague pelo app do
      banco e confira o nome do recebedor e o valor. Depois confirme em
      **Administração → Conferir pagamentos**.
- [ ] **Simulador:** abra uma aula do curso de exemplo e veja se o Wokwi aparece dentro da página.
- [ ] **Backup:** o cPanel tem **Backup** — baixe uma cópia do banco `..._cursos` de vez em quando.

## Atualizar para uma versão nova

1. No **Gerenciador de arquivos**, abra `public_html`.
2. **Carregar** o `cursos.zip` novo → botão direito nele → **Extract** (Extrair), confirmando substituir os arquivos.
3. Abra `https://www.SEUSITE.com.br/cursos/` normalmente. As tabelas novas do banco são criadas sozinhas.

Seus cursos, alunos e o `config.php` (senha do banco e Pix) não são apagados: o pacote não traz o `config.php`.

## Aulas particulares (presenciais)

1. **Administração → Aulas particulares:** cadastre o aluno, o e-mail do professor (ele precisa ter conta)
   e o nome e WhatsApp do responsável.
2. **O professor**, pelo celular, abre **Aulas particulares** e toca em **Cheguei agora** ao chegar.
   O horário registrado é o do servidor, não o do celular. Se esquecer, pode informar o horário, que fica
   marcado como "informado".
3. Ao terminar, ele descreve o **conteúdo apresentado** e toca em **Finalizar aula**.
4. Aparece o botão **Enviar pelo WhatsApp**, com o link para o responsável. O responsável abre o link
   (sem senha), vê horário de chegada, saída e conteúdo, e toca em **Confirmo** ou **Algo não confere**.
5. Em **Administração → Aulas particulares** você vê o resumo do mês por professor (aulas, confirmadas,
   horas confirmadas), útil para pagar os professores.

## Acompanhamento por aluno

Clique no nome do aluno no painel da escola, nas correções ou nas aulas particulares, ou busque pelo
e-mail em **Administração**. Mostra cada curso aula por aula (com a data de conclusão), as entregas e notas,
os certificados e todas as aulas particulares. O aluno vê o próprio em **Meu painel → Meu acompanhamento**.

## Mudar o Pix ou o nome da plataforma depois

No Gerenciador de arquivos, abra `public_html/cursos/config.php` → **Editar** e altere as
linhas de `pix` ou `nome_site`. Salve. Esse arquivo tem a senha do banco: não compartilhe.

## Como funciona o dia a dia

| Quem | Onde | O que faz |
|---|---|---|
| Você (admin) | Administração | Cria cursos, aulas, escolas e licenças; confere os Pix; gera nova senha para qualquer conta |
| Coordenador da escola | Escola | Turmas, códigos de convite, vagas, progresso dos alunos, nova senha para alunos |
| Professor | Correções e Aulas particulares | Corrige as entregas das turmas dele; registra chegada e conteúdo das aulas particulares |
| Aluno | Meu painel | Estuda, usa o simulador, envia projetos, recebe o certificado |

**Para uma escola começar:** em Administração, crie a escola (aparece um código de administrador
para enviar ao responsável) e libere o curso com o número de vagas. O responsável cria a conta
com esse código, gera o código da turma e distribui aos alunos.

**Venda individual:** o aluno clica em Comprar, paga o Pix pelo QR Code e clica em "Já paguei".
Você confere no extrato e clica em **Confirmar recebimento**; o curso libera na hora.

## O que ainda não existe

- Envio de e-mails (recuperação de senha é por link gerado pelo admin ou pela escola)
- Confirmação automática do Pix e cartão de crédito (exigiriam contratar um serviço de cobrança)
- Upload de arquivos nas entregas (o aluno envia link do simulador, código ou link de vídeo)
- Editar ou reordenar aulas já criadas (dá para criar e excluir)
