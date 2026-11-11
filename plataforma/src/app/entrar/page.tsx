import Link from "next/link";
import { redirect } from "next/navigation";
import { Aviso, type SP } from "@/componentes/Aviso";
import { usuarioAtual } from "@/lib/auth";
import { entrar } from "../acoes/conta";

export default async function Entrar({ searchParams }: { searchParams: SP }) {
  if (await usuarioAtual()) redirect("/painel");
  return (
    <main className="estreito">
      <h1>Entrar</h1>
      <Aviso sp={await searchParams} />
      <form action={entrar} className="pilha cartao">
        <label>E-mail<input name="email" type="email" autoComplete="email" required /></label>
        <label>Senha<input name="senha" type="password" autoComplete="current-password" required /></label>
        <button className="btn">Entrar</button>
      </form>
      <p>Ainda não tem conta? <Link href="/cadastro">Criar conta</Link></p>
    </main>
  );
}
