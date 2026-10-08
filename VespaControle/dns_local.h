// ============================================================================
//  dns_local.h - Servidor DNS minimo da Vespa
//
//  Responde com o IP da placa SOMENTE para:
//    - os enderecos que os celulares/PCs usam para testar a internet
//      (assim eles nao abandonam a rede da Vespa)
//    - o nome da placa (ex.: "vespa")
//  Todos os outros nomes recebem "nao existe" na hora. Isso impede que os
//  aplicativos do celular (WhatsApp, Instagram...) fiquem mandando trafego
//  para a placa e congestionando o servidor web.
// ============================================================================
#pragma once

#include <WiFiUdp.h>

class LocalDNS {
 public:
  void begin(IPAddress ip, const char *boardName) {
    _ip = ip;
    _boardName = boardName;
    _udp.begin(53);
  }

  void process() {
    int len = _udp.parsePacket();
    if (len <= 0) return;
    if (len <= 12 || len > (int)sizeof(_buf)) {   // pacote invalido: descarta
      while (_udp.available()) _udp.read();
      return;
    }
    len = _udp.read(_buf, sizeof(_buf));
    if (len <= 12) return;

    // somente consultas padrao com 1 pergunta
    if ((_buf[2] & 0x80) || ((_buf[2] >> 3) & 0x0F) != 0 || _buf[4] != 0 || _buf[5] != 1) return;

    // le o nome consultado (ex.: "connectivitycheck.gstatic.com")
    char name[128];
    int ni = 0, p = 12;
    while (p < len && _buf[p] != 0) {
      uint8_t l = _buf[p++];
      if (l > 63 || p + l > len) return;
      if (ni && ni < 127) name[ni++] = '.';
      for (uint8_t i = 0; i < l; i++) {
        char c = (char)tolower(_buf[p++]);
        if (ni < 127) name[ni++] = c;
      }
    }
    name[ni] = 0;
    p++;                                   // byte zero do fim do nome
    if (p + 4 > len) return;
    uint16_t qtype = (_buf[p] << 8) | _buf[p + 1];
    int n = p + 4;                         // fim da pergunta

    bool known = isKnownName(name);
    bool answerA = known && qtype == 1;    // tipo A (IPv4)

    // monta a resposta reaproveitando o pacote da pergunta
    _buf[2] = 0x84 | (_buf[2] & 0x01);     // resposta, autoritativa, mantem RD
    _buf[3] = known ? 0x00 : 0x03;         // 0 = ok, 3 = nome nao existe
    _buf[6] = 0; _buf[7] = answerA ? 1 : 0;
    _buf[8] = _buf[9] = _buf[10] = _buf[11] = 0;

    if (answerA) {
      const uint8_t ans[] = {0xC0, 0x0C, 0x00, 0x01, 0x00, 0x01,
                             0x00, 0x00, 0x00, 0x3C, 0x00, 0x04};
      memcpy(_buf + n, ans, sizeof(ans));
      n += sizeof(ans);
      for (int i = 0; i < 4; i++) _buf[n++] = _ip[i];
    }

    _udp.beginPacket(_udp.remoteIP(), _udp.remotePort());
    _udp.write(_buf, n);
    _udp.endPacket();
  }

 private:
  bool isKnownName(const char *name) {
    static const char *const names[] = {
      // Android / Chrome
      "connectivitycheck.gstatic.com", "connectivitycheck.android.com",
      "clients3.google.com", "clients1.google.com", "clients.l.google.com",
      // Apple
      "captive.apple.com", "www.apple.com", "www.appleiphonecell.com",
      // Windows
      "www.msftconnecttest.com", "msftconnecttest.com", "www.msftncsi.com",
      // Firefox / Linux
      "detectportal.firefox.com", "nmcheck.gnome.org",
    };
    for (const char *n : names) {
      if (strcmp(name, n) == 0) return true;
    }
    // nome da placa: "vespa", "vespa.local", "vespa.robo", ...
    size_t bl = strlen(_boardName);
    return strncmp(name, _boardName, bl) == 0 && (name[bl] == 0 || name[bl] == '.');
  }

  WiFiUDP _udp;
  IPAddress _ip;
  const char *_boardName = "";
  uint8_t _buf[512];
};
