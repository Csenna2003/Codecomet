import Link from "next/link";
import { Aviso, type SP } from "@/componentes/Aviso";
import { buscarRedefinicao } from "@/lib/senha";
import { redefinirSenha } from "../../acoes/conta";

export default async function Redefinir({ params, searchParams }: { params: Promise<{ token: string }>; searchParams: SP }) {
  const { token } = await params;
  const pedido = await buscarRedefinicao(token);
  return (
    <main className="estreito">
      <h1>Criar nova senha</h1>
      <Aviso sp={await searchParams} />
      {pedido ? (
        <form action={redefinirSenha} className="pilha cartao">
          <p>Olá, {pedido.usuario.nome.split(" ")[0]}! Escolha uma nova senha.</p>
          <input type="hidden" name="token" value={token} />
          <label>Nova senha (mínimo de 8 caracteres)
            <input name="senha" type="password" minLength={8} autoComplete="new-password" required />
          </label>
          <button className="btn">Salvar nova senha</button>
        </form>
      ) : (
        <p className="aviso erro">Este link não vale mais. Peça outro ao seu professor ou à equipe Codecomet.</p>
      )}
      <Link href="/entrar">Ir para a tela de entrada</Link>
    </main>
  );
}
