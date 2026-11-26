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

## Seus cursos prontos (Robótica, Programação, Formação Maker e Modelagem 3D)

Em **Administração → Cursos prontos da Adoro Robótica** aparecem os cursos prontos. Os três primeiros foram montados a partir das suas
apostilas do Google Drive; o de Modelagem 3D foi escrito para a plataforma, seguindo a sequência
do seu material (Tinkercad e depois Fusion 360):

| Curso | Módulos | Aulas | Carga |
|---|---|---|---|
| Robótica e Programação (versão remota) | 6 | 102 | 96h |
| Curso de Programação | 6 | 78 | 72h |
| Formação Maker (Currículo de 70 horas) | 6 | 60 | 70h |
| Modelagem 3D: do Tinkercad ao Fusion 360 | 6 | 51 | 54h |

Clique em **Importar curso**. Ele entra como **rascunho**, com preço zero: revise, defina o preço e o
status **Publicado** em **Editar conteúdo**. Cada módulo termina com um projeto que o aluno entrega e o
professor corrige pela rubrica da apostila. O "Guia do professor" de cada aula aparece em
**Notas do professor**, só para a equipe.

**Atualizar textos:** quando sai uma versão com correções nos cursos prontos, aparece o aviso
"Há N correções de texto" e o botão **Atualizar textos** no cartão do curso, em **Administração → Cursos
prontos**. Ele só troca os textos que ainda estão iguais ao que foi importado: o que você editou, o
progresso dos alunos, as entregas e o preço não mudam.

## Editar aulas pela tela

Em **Administração → curso → Editar conteúdo**, cada aula tem os botões **Editar**, **↑ ↓** (mudar a
ordem) e **Excluir**. Os módulos também têm **↑ ↓**, **Renomear módulo** e, quando estão vazios,
**Excluir módulo**.

Em **Editar** você muda título, tipo, duração, módulo (a aula vai para o fim do módulo escolhido),
texto, links de vídeo e simulador, perguntas do quiz, enunciado e critérios do exercício e as notas do
professor. **Salvar e ver a aula** mostra como o aluno vai ver. Quando você abre uma aula como
administrador, o aviso no topo tem o link **Editar esta aula**.

O texto aceita formatação simples: `## ` faz subtítulo, `- ` faz lista, `1. ` lista numerada,
`[[Dica]] texto` faz um quadro de destaque, `**texto**` fica em negrito e um bloco entre linhas com
três crases vira código. No quiz, separe as perguntas com uma linha em branco e marque a resposta
certa com `*` e as outras com `-`.

Se um exercício já tem entregas de alunos, o tipo da aula fica travado (para não perder as entregas).

## Fotos e arquivos nas entregas

Ao enviar um projeto, o aluno pode anexar até 4 fotos ou arquivos: fotos do robô montado (no celular
dá para tirar a foto na hora), PDF, código (.ino, .py, .txt), projeto do Scratch (.sb3) ou .zip.

- Nos projetos dos cursos prontos, o aluno manda o link **ou** as fotos.
- Ao criar ou editar um exercício, o tipo **Fotos ou arquivos do projeto** exige pelo menos uma foto.
- As fotos são reduzidas no próprio celular antes de enviar e de novo no servidor (no máximo 1600 px),
  e perdem os dados escondidos da câmera, como a localização GPS.
- Só veem as fotos o próprio aluno, o professor da turma, a coordenação da escola e você. Elas ficam
  na pasta `cursos/arquivos/`, que não abre pelo navegador. Ao atualizar a plataforma, essa pasta não é
  apagada.

Se aparecer o aviso de que o arquivo passou do limite, aumente o limite em cPanel → **MultiPHP INI
Editor** → `upload_max_filesize` (ex.: 12M) e `post_max_size` (ex.: 50M). O arquivo `cursos/.user.ini`
já pede esses valores, mas algumas hospedagens ignoram.

## Relatório de progresso das turmas

Em **Escola → 📊 Relatório de progresso** (ou **Relatório da turma**, ao lado de cada turma), a
coordenação escolhe a turma e o curso e vê:

- quantos alunos estão no curso, o progresso médio, quantos têm certificado e a nota média nos projetos;
- quem **precisa de atenção**: alunos que não começaram ou estão parados há 14 dias ou mais;
- cada aluno com progresso, módulo em que está, projetos aprovados, nota média e última atividade;
- o andamento de cada módulo e a situação de cada projeto do curso.

Dá para **imprimir ou salvar em PDF** (para levar à reunião com a escola) e **baixar a planilha**.
O professor vê o relatório das turmas dele em **Correções → 📊 Relatório das turmas**, e você vê o de
qualquer escola em **Administração → Escolas → Relatório**.

Alunos de escola matriculados por você (em **Criar acessos** com escola, ou em **Matricular**) ficam
ligados à escola, para o professor e a coordenação corrigirem os projetos e acompanharem no relatório.

## Aplicativo no celular e no computador

A plataforma também funciona como aplicativo, sem loja e sem custo. No painel do aluno aparece
**Instale o aplicativo**:

- **Android e computador (Chrome ou Edge):** botão **Instalar aplicativo**. O ícone da Adoro Robótica
  vai para a tela inicial e abre em tela cheia.
- **iPhone e iPad (Safari):** tocar em **Compartilhar** (quadrado com seta) e em
  **Adicionar à Tela de Início**.

O aplicativo é a mesma plataforma: tudo que você muda no site aparece no aplicativo na hora, sem
atualizar nada. Sem internet, ele mostra um aviso em vez de uma tela de erro. Para estar na Google Play
(US$ 25, uma vez) ou na App Store (US$ 99 por ano) seria preciso uma conta de desenvolvedor; dá para
fazer depois, usando esta mesma versão.

## Criar os acessos dos alunos e das escolas (login e senha)

**Alunos:** Administração → **Alunos** → **Criar acessos**. Escreva um nome por linha (ou “Nome; e-mail”),
escolha, se quiser, a escola, a turma e o curso, e clique em **Criar acessos**. Aparece a tela com o link,
o login e a senha provisória de cada um, com botões para **enviar pelo WhatsApp**, **imprimir cartões**,
**baixar planilha** e **copiar tudo**. As senhas só aparecem nessa tela: envie ou imprima na hora.

- Quem não tem e-mail entra com um **usuário** (ex.: `ana.souza`), ótimo para crianças.
- No primeiro acesso aparece um aviso para o aluno trocar a senha provisória por uma dele.
- Esqueceu a senha? No cadastro do aluno, **Gerar nova senha provisória** (a anterior deixa de valer).

**Escolas:** ao criar a escola em Administração, preencha **Responsável da escola**: o acesso da coordenação
é criado na hora, com login e senha. A coordenação também cria os acessos dos próprios alunos e professores
no painel **Escola → Criar acessos** e gera nova senha com o botão **Nova senha** ao lado de cada aluno.

## Matricular alunos

Há quatro jeitos de um aluno entrar num curso:

1. **Você matricula:** Administração → **Alunos** → **Editar** → seção **Matrículas** → escolha o curso.
   Ou, na página do curso (Administração → **Editar conteúdo**), em **Alunos matriculados**, pelo e-mail do aluno.
   Tipo "Matrícula" (pagamento por fora) ou "Bolsa / cortesia", com prazo opcional. O aluno recebe e-mail.
2. **Pela escola:** o aluno usa o código da turma ao criar a conta, ou você o coloca na escola em
   **Editar → Escola e turma**. Ele recebe os cursos da licença da escola, se houver vaga.
3. **Compra:** o aluno clica em **Comprar curso** e paga o Pix; você confirma em **Conferir pagamentos**.
4. **Curso gratuito:** o aluno clica em **Matricular-se grátis**.

O curso precisa estar **Publicado** para receber matrículas. Cancelar uma matrícula encerra o acesso,
mas mantém o histórico e os certificados.

## Cadastros (alunos, escolas e professores)

- **Alunos:** Administração → **Alunos**. Busca por nome ou e-mail e filtro por situação financeira.
  Em **Editar** ficam dados pessoais, CPF, endereço (o CEP preenche o resto), responsável e o
  **financeiro**: responsável financeiro, mensalidade, vencimento, forma de pagamento e situação.
  O próprio aluno atualiza telefone, endereço e senha em **Meu painel → Meus dados**.
- **Escolas:** Administração → **Editar cadastro** na escola (ou **Dados da escola** no painel dela).
  A coordenação edita os dados de contato e endereço; o **contrato e o financeiro** só você vê e altera.
  Desmarcar "Escola ativa" suspende o acesso de alunos e equipe pela escola.
- **Professores:** Administração → **Professores**. Cadastre pelo e-mail; se a pessoa não tiver conta,
  ela recebe um e-mail para criar a senha. No perfil: endereço, competências, disponibilidade
  (dias × manhã/tarde/noite), CPF, chave Pix e **valor da hora-aula** (este só você altera).
  A lista filtra por competência, dia e período. O professor preenche o próprio perfil em
  **Meu painel → Meu perfil de professor**.
- No resumo mensal das aulas particulares aparece o **valor a pagar** a cada professor
  (horas confirmadas × valor da hora) e a chave Pix dele.

CPFs e dados financeiros só aparecem para a administração da plataforma.

## E-mails automáticos

A plataforma envia e-mails pelo próprio servidor da HostGator (sem contratar nada):
boas-vindas no cadastro (e aviso ao responsável, se o aluno for menor), "Esqueci minha senha",
correção de entrega, pagamento confirmado, certificado emitido, aviso de Pix para você conferir
e o link de confirmação da aula particular (se o responsável tiver e-mail cadastrado).

- Remetente: `nao-responda@` o seu domínio. Para os e-mails não caírem no spam, crie essa conta em
  **cPanel → Contas de e-mail** (ex.: `nao-responda@adororobotica.com.br`).
- Para usar outro remetente, acrescente no `config.php` a linha
  `'email_remetente' => 'contato@adororobotica.com.br',` antes do último `];`.
- **Administração → E-mails enviados** mostra os últimos envios e tem o botão
  **Enviar um e-mail de teste para mim**.

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

- Confirmação automática do Pix e cartão de crédito (exigiriam contratar um serviço de cobrança)
