export type Plataforma = "TINKERCAD" | "WOKWI";

function hostDe(url: string) {
  try {
    const u = new URL(url);
    if (u.protocol !== "https:") return null;
    return { u, host: u.hostname.replace(/^www\./, "") };
  } catch {
    return null;
  }
}

export function plataformaDe(url: string): Plataforma | null {
  const h = hostDe(url);
  if (!h) return null;
  if (h.host === "tinkercad.com") return "TINKERCAD";
  if (h.host === "wokwi.com") return "WOKWI";
  return null;
}

/** Converte o link de compartilhamento do simulador na URL usada no iframe da aula. */
export function embedSimulador(url: string): { plataforma: Plataforma; embed: string; original: string } | null {
  const h = hostDe(url);
  if (!h) return null;
  if (h.host === "tinkercad.com") {
    const m = h.u.pathname.match(/\/(?:things|embed)\/([A-Za-z0-9]+)/);
    if (!m) return null;
    return { plataforma: "TINKERCAD", embed: `https://www.tinkercad.com/embed/${m[1]}?editbtn=1`, original: url };
  }
  if (h.host === "wokwi.com" && h.u.pathname.startsWith("/projects/")) {
    return { plataforma: "WOKWI", embed: `https://wokwi.com${h.u.pathname}`, original: url };
  }
  return null;
}

/** YouTube/Vimeo -> URL de player embutido. */
export function embedVideo(url: string): string | null {
  const h = hostDe(url);
  if (!h) return null;
  if (h.host === "youtu.be") return `https://www.youtube-nocookie.com/embed/${h.u.pathname.slice(1)}`;
  if (h.host === "youtube.com" || h.host === "m.youtube.com") {
    const id = h.u.searchParams.get("v") ?? h.u.pathname.match(/\/(?:embed|shorts)\/([^/]+)/)?.[1];
    return id ? `https://www.youtube-nocookie.com/embed/${id}` : null;
  }
  if (h.host === "vimeo.com") {
    const id = h.u.pathname.match(/\/(\d+)/)?.[1];
    return id ? `https://player.vimeo.com/video/${id}` : null;
  }
  return null;
}

export function ehHttps(url: string) {
  return hostDe(url) !== null;
}
