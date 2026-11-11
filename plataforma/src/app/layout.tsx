import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";
import { ehAdmin, ehEquipe, orgsGeridas, usuarioAtual } from "@/lib/auth";
import { sair } from "./acoes/conta";

export const metadata: Metadata = {
  title: "Codecomet — Robótica e Programação",
  description: "Cursos de robótica e programação com simulador, projetos corrigidos e certificado.",
};

export default async function Layout({ children }: { children: React.ReactNode }) {
  const u = await usuarioAtual();
  return (
    <html lang="pt-BR">
      <body>
        <header className="topo">
          <div className="dentro">
            <Link href="/" className="marca">☄️ Codecomet</Link>
            <nav>
              <Link href="/">Cursos</Link>
              {u ? (
                <>
                  <Link href="/painel">Meu painel</Link>
                  {ehEquipe(u) && <Link href="/professor">Correções</Link>}
                  {(ehAdmin(u) || orgsGeridas(u).length > 0) && <Link href="/escola">Escola</Link>}
                  {ehAdmin(u) && <Link href="/admin">Administração</Link>}
                  <form action={sair}>
                    <button className="link">Sair ({u.nome.split(" ")[0]})</button>
                  </form>
                </>
              ) : (
                <>
                  <Link href="/entrar">Entrar</Link>
                  <Link href="/cadastro" className="btn mini">Criar conta</Link>
                </>
              )}
            </nav>
          </div>
        </header>
        {children}
      </body>
    </html>
  );
}
