import Link from "next/link";
import { redirect } from "next/navigation";
import { Aviso, type SP } from "@/componentes/Aviso";
import { usuarioAtual } from "@/lib/auth";
import { cadastrar } from "../acoes/conta";

export default async function Cadastro({ searchParams }: { searchParams: SP }) {
  if (await usuarioAtual()) redirect("/painel");
  const sp = await searchParams;
  return (
    <main className="estreito">
      <h1>Criar conta</h1>
      <Aviso sp={sp} />
      <form action={cadastrar} className="pilha cartao">
        <label>Nome completo<input name="nome" autoComplete="name" required /></label>
        <label>E-mail<input name="email" type="email" autoComplete="email" required /></label>
        <label>Senha (mínimo de 8 caracteres)
          <input name="senha" type="password" minLength={8} autoComplete="new-password" required />
        </label>
        <label>Data de nascimento<input name="nascimento" type="date" required /></label>
        <label>Código da escola (se você recebeu um)
          <input name="codigo" defaultValue={sp.codigo ?? ""} autoCapitalize="characters" />
        </label>
        <fieldset className="pilha" style={{ display: "grid", gap: ".75rem" }}>
          <legend>Se o aluno tem menos de 18 anos</legend>
          <label>Nome do responsável<input name="responsavelNome" /></label>
          <label>E-mail do responsável<input name="responsavelEmail" type="email" /></label>
          <label className="marcar">
            <input type="checkbox" name="consentimento" />
            <span>Sou o responsável e autorizo o cadastro e o uso dos dados do aluno para fins educacionais.</span>
          </label>
        </fieldset>
        <button className="btn">Criar conta</button>
      </form>
      <p>Já tem conta? <Link href="/entrar">Entrar</Link></p>
    </main>
  );
}
