# Conversão das apostilas em cursos da plataforma

Gera os arquivos `cursos/app/conteudo/*.json`, que aparecem em **Administração → Cursos prontos**.

Fonte: as apostilas da pasta **Adoro Robotica** do Google Drive, exportadas como texto
(uma por arquivo, com o mesmo nome do original e extensão `.txt`):

| Curso | Arquivos usados |
|---|---|
| Robótica e Programação | `Professor_Modulo_N_*.docx` da pasta *Curso de robótica 2 Anos Remoto* |
| Curso de Programação | `Professor_Modulo_N_*.pdf` da pasta *Curso de programação completo* |
| Formação Maker | `Apostila_Completa_Aluno_Curriculo_Maker.pdf` e `Apostila_Completa_Professor_Curriculo_Maker.pdf` |

Usamos a versão do professor porque ela contém todo o conteúdo do aluno; o "Guia do professor"
de cada aula vira as **Notas do professor**, visíveis só para a equipe.

O texto exportado perde as quebras de linha dos quadros de código. Por isso o código de referência
foi reconstruído à mão em `codigos.py` (Programação) e `codigos_rob.py` (Robótica). Se uma apostila
mudar o código de uma aula, atualize o bloco correspondente nesses arquivos.

```bash
python3 robotica.py    PASTA_DOS_TXT ../../cursos/app/conteudo/1-robotica-e-programacao.json
python3 programacao.py PASTA_DOS_TXT ../../cursos/app/conteudo/2-curso-de-programacao.json
python3 maker.py       PASTA_DOS_TXT ../../cursos/app/conteudo/3-formacao-maker.json
```
