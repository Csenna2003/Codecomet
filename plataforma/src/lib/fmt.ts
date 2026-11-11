export const brl = (centavos: number) =>
  centavos === 0
    ? "Gratuito"
    : (centavos / 100).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

export const data = (d: Date) => d.toLocaleDateString("pt-BR", { timeZone: "America/Sao_Paulo" });

export const rotuloNivel = { INICIANTE: "Iniciante", INTERMEDIARIO: "Intermediário", AVANCADO: "Avançado" };
export const rotuloTipoAula = {
  VIDEO: "Vídeo",
  TEXTO: "Leitura",
  QUIZ: "Quiz",
  SIMULADOR: "Simulador",
  EXERCICIO: "Exercício",
};
export const rotuloPapel = {
  ALUNO: "Aluno",
  PROFESSOR: "Professor",
  COORDENADOR: "Coordenador",
  ADMIN_ORG: "Administrador da escola",
};
export const rotuloEntrega = {
  ENVIADA: "Aguardando correção",
  APROVADA: "Aprovada",
  PRECISA_AJUSTE: "Precisa de ajuste",
};

export function codigoAleatorio(tamanho: number) {
  // Sem 0/O/1/I para não confundir quem digita.
  const alfabeto = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  const bytes = crypto.getRandomValues(new Uint8Array(tamanho));
  return Array.from(bytes, (b) => alfabeto[b % alfabeto.length]).join("");
}

export function slugify(s: string) {
  return s
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

export type Pergunta = { pergunta: string; opcoes: string[]; correta: number };

/**
 * Formato de autoria do quiz: blocos separados por linha em branco;
 * primeira linha é a pergunta, "*" marca a opção correta e "-" as demais.
 */
export function parseQuiz(texto: string): Pergunta[] {
  const perguntas: Pergunta[] = [];
  for (const bloco of texto.split(/\n\s*\n/)) {
    const linhas = bloco.split("\n").map((l) => l.trim()).filter(Boolean);
    if (linhas.length < 3) continue;
    const opcoes: string[] = [];
    let correta = -1;
    for (const l of linhas.slice(1)) {
      if (l.startsWith("*")) correta = opcoes.length;
      if (l.startsWith("*") || l.startsWith("-")) opcoes.push(l.slice(1).trim());
    }
    if (correta >= 0 && opcoes.length >= 2) perguntas.push({ pergunta: linhas[0], opcoes, correta });
  }
  return perguntas;
}
