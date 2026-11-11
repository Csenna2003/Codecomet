// Dados de demonstração. Rode uma vez: npm run db:seed
import bcrypt from "bcryptjs";
import { PrismaClient } from "@prisma/client";
import { aplicarLicencas } from "../src/lib/escola";

const db = new PrismaClient();
const WOKWI_ESP32 = "https://wokwi.com/projects/new/esp32";

async function main() {
  if (await db.usuario.count()) {
    console.log("O banco já tem usuários; seed ignorado.");
    return;
  }
  const senha = process.env.SEED_SENHA ?? "codecomet123";
  const senhaHash = await bcrypt.hash(senha, 10);
  const pessoa = (nome: string, email: string, extra = {}) =>
    db.usuario.create({ data: { nome, email, senhaHash, dataNascimento: new Date("1990-01-01"), ...extra } });

  await pessoa("Admin Codecomet", "admin@codecomet.dev", { papelGlobal: "ADMIN_PLATAFORMA" });
  const coord = await pessoa("Carla Coordenadora", "coordenacao@escola-demo.dev");
  const prof = await pessoa("Paulo Professor", "professor@escola-demo.dev");
  const ana = await pessoa("Ana Aluna", "ana@escola-demo.dev", {
    dataNascimento: new Date("2013-05-10"),
    responsavelNome: "Marta (mãe da Ana)",
    responsavelEmail: "marta@exemplo.dev",
    consentimentoEm: new Date(),
  });
  await pessoa("Igor Individual", "igor@exemplo.dev");

  const curso = await db.curso.create({
    data: {
      slug: "robotica-com-esp32-primeiros-passos",
      titulo: "Robótica com ESP32: primeiros passos",
      descricao:
        "Do primeiro LED ao robô que desvia de obstáculos. Você monta no simulador, programa e entrega projetos corrigidos pelo professor.",
      nivel: "INICIANTE",
      cargaHoraria: 12,
      precoCentavos: 19700,
      status: "PUBLICADO",
      modulos: {
        create: [
          {
            ordem: 1,
            titulo: "Acendendo o primeiro LED",
            aulas: {
              create: [
                {
                  ordem: 1,
                  tipo: "TEXTO",
                  titulo: "Como funciona um microcontrolador",
                  duracaoMin: 10,
                  conteudo:
                    "O ESP32 é um pequeno computador que lê sensores e comanda luzes e motores através dos seus pinos (GPIOs).\n\nUm pino configurado como SAÍDA pode ficar em nível ALTO (3,3 V) ou BAIXO (0 V). Ligando um LED com um resistor de 220 Ω entre o pino e o GND, o LED acende quando o pino está em ALTO.\n\nNa próxima aula você vai montar esse circuito no simulador.",
                },
                {
                  ordem: 2,
                  tipo: "SIMULADOR",
                  titulo: "Montando o circuito no simulador",
                  duracaoMin: 20,
                  simuladorUrl: WOKWI_ESP32,
                  conteudo:
                    "1. Adicione um LED e um resistor de 220 Ω.\n2. Ligue o GPIO16 ao resistor, o resistor ao ânodo do LED e o cátodo ao GND.\n3. No código, use pinMode(16, OUTPUT) e digitalWrite(16, HIGH).\n4. Aperte o botão de iniciar e veja o LED acender.",
                },
                {
                  ordem: 3,
                  tipo: "QUIZ",
                  titulo: "Quiz: pinos e LEDs",
                  duracaoMin: 5,
                  quiz: [
                    {
                      pergunta: "Para que serve o resistor em série com o LED?",
                      opcoes: ["Limitar a corrente e proteger o LED", "Deixar o LED mais brilhante", "Guardar energia"],
                      correta: 0,
                    },
                    {
                      pergunta: "Qual comando coloca o pino 16 em nível ALTO?",
                      opcoes: ["pinMode(16, HIGH)", "digitalWrite(16, HIGH)", "analogRead(16)"],
                      correta: 1,
                    },
                    {
                      pergunta: "Em qual terminal do circuito o cátodo do LED é ligado?",
                      opcoes: ["3,3 V", "GND", "Em nenhum"],
                      correta: 1,
                    },
                  ],
                },
                {
                  ordem: 4,
                  tipo: "EXERCICIO",
                  titulo: "Desafio: semáforo com 3 LEDs",
                  duracaoMin: 40,
                  simuladorUrl: WOKWI_ESP32,
                  exercicio: {
                    create: {
                      enunciado:
                        "Monte um semáforo com LEDs vermelho, amarelo e verde nos GPIOs 16, 17 e 18.\n\nSequência: verde por 3 s, amarelo por 1 s, vermelho por 3 s, repetindo para sempre.\n\nSalve o projeto na sua conta do Wokwi ou do Tinkercad e envie o link.",
                      rubrica: [
                        "Os três LEDs têm resistor em série",
                        "A sequência verde → amarelo → vermelho está correta",
                        "Os tempos são 3 s, 1 s e 3 s",
                        "O código está organizado e comentado",
                      ],
                      tipoEntrega: "LINK_SIMULADOR",
                      maxTentativas: 3,
                    },
                  },
                },
              ],
            },
          },
          {
            ordem: 2,
            titulo: "Sensores: medindo o mundo",
            aulas: {
              create: [
                {
                  ordem: 1,
                  tipo: "TEXTO",
                  titulo: "Sensor ultrassônico HC-SR04",
                  duracaoMin: 15,
                  conteudo:
                    "O HC-SR04 mede distância emitindo um pulso de ultrassom e cronometrando o eco.\n\nDistância (cm) = duração do eco (µs) × 0,0343 ÷ 2.\n\nAtenção no hardware real: o pino ECHO sai em 5 V e o ESP32 só aceita 3,3 V — use um divisor de tensão (1 kΩ + 2 kΩ).",
                },
                {
                  ordem: 2,
                  tipo: "EXERCICIO",
                  titulo: "Projeto final: alarme de proximidade",
                  duracaoMin: 60,
                  simuladorUrl: WOKWI_ESP32,
                  exercicio: {
                    create: {
                      enunciado:
                        "Use o HC-SR04 (TRIG no GPIO19, ECHO no GPIO23) para acender o LED vermelho quando um objeto estiver a menos de 20 cm e o verde quando estiver mais longe.\n\nMostre a distância no Monitor Serial a cada 0,5 s. Envie o link do projeto e cole o código.",
                      rubrica: [
                        "A distância aparece em cm no Monitor Serial",
                        "O LED vermelho acende abaixo de 20 cm",
                        "O LED verde acende acima de 20 cm",
                      ],
                      tipoEntrega: "LINK_SIMULADOR",
                    },
                  },
                },
                {
                  ordem: 3,
                  tipo: "TEXTO",
                  titulo: "Extra: levando o projeto para a placa Vespa",
                  obrigatoria: false,
                  duracaoMin: 10,
                  conteudo:
                    "Quem tem o kit com a RoboCore Vespa pode gravar o mesmo programa na placa real pela Arduino IDE.\n\nO firmware \"Vespa Controle\" (pasta VespaControle do repositório) mostra LEDs, servos e sensores em um painel pelo Wi-Fi da própria placa.",
                },
              ],
            },
          },
        ],
      },
    },
  });

  const avancado = await db.curso.create({
    data: {
      slug: "robo-movel-com-servos",
      titulo: "Robô móvel com servos e controle por Wi-Fi",
      descricao: "Continuação da trilha: servomotores, joystick web e controle remoto do robô.",
      nivel: "INTERMEDIARIO",
      cargaHoraria: 16,
      precoCentavos: 24700,
      status: "RASCUNHO",
    },
  });
  await db.trilha.create({
    data: {
      titulo: "Do zero ao robô controlado por Wi-Fi",
      descricao: "A sequência recomendada para quem está começando.",
      cursos: { create: [{ cursoId: curso.id, ordem: 1 }, { cursoId: avancado.id, ordem: 2 }] },
    },
  });

  const org = await db.organizacao.create({ data: { nome: "Escola Demonstração" } });
  await db.membro.createMany({
    data: [
      { orgId: org.id, usuarioId: coord.id, papel: "COORDENADOR" },
      { orgId: org.id, usuarioId: prof.id, papel: "PROFESSOR" },
      { orgId: org.id, usuarioId: ana.id, papel: "ALUNO" },
    ],
  });
  const turma = await db.turma.create({
    data: { orgId: org.id, nome: "7º ano A", periodo: "2027", professorId: prof.id },
  });
  await db.turmaAluno.create({ data: { turmaId: turma.id, usuarioId: ana.id } });
  await db.convite.create({ data: { orgId: org.id, turmaId: turma.id, codigo: "DEMO7ANO" } });
  await db.licenca.create({ data: { orgId: org.id, cursoId: curso.id, vagasTotal: 30 } });
  await aplicarLicencas(org.id);

  console.log(`Seed concluído. Senha de todos os usuários de demonstração: ${senha}`);
  console.log("  admin@codecomet.dev          administrador da plataforma");
  console.log("  coordenacao@escola-demo.dev  coordenadora da Escola Demonstração");
  console.log("  professor@escola-demo.dev    professor do 7º ano A");
  console.log("  ana@escola-demo.dev          aluna com acesso pela licença da escola");
  console.log("  igor@exemplo.dev             aluno individual, sem cursos");
  console.log("  Código de convite da turma:  DEMO7ANO");
}

main().finally(() => db.$disconnect());
