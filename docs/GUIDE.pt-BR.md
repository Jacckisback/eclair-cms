<p align="center">
  <a href="GUIDE.md">Français</a> · <a href="GUIDE.en.md">English</a> · <a href="GUIDE.es.md">Español</a> · <a href="GUIDE.de.md">Deutsch</a> · <a href="GUIDE.it.md">Italiano</a> · <b>Português</b> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Guia do Éclair CMS

Tudo o que você precisa saber para instalar, usar, proteger e manter o Éclair. Para a apresentação, volte ao [README](../README.pt-BR.md).

## Sumário

1. [Instalação](#instalação)
2. [Recursos em detalhe](#recursos-em-detalhe)
3. [Uso no dia a dia](#uso-no-dia-a-dia)
4. [Segurança](#segurança)
5. [Atualizar, fazer backup, mudar de hospedagem](#atualizar-fazer-backup-mudar-de-hospedagem)
6. [Solução de problemas](#solução-de-problemas) (incluindo [Nginx](#nginx))
7. [Para desenvolvedores](#para-desenvolvedores)

---

## Instalação

### Requisitos

- **PHP 8.1 ou superior** (8.3 recomendado), com as extensões `mbstring`, `json`, `dom` e `session` (ativas por padrão em quase todo lugar).
- Recomendado: `gd` (imagens WebP), `zip` (backup em um clique), `curl` (IndexNow).
- Um servidor **Apache ou LiteSpeed** (praticamente todas as hospedagens compartilhadas: Hostinger, IONOS, OVHcloud…). Para Nginx, veja [mais abaixo](#nginx).
- Um certificado HTTPS (gratuito com Let's Encrypt em quase todas as hospedagens).

O assistente verifica tudo isso para você e explica o que corrigir.

### Passo a passo

1. Baixe o arquivo [`eclair-cms-1.1.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip) (315 KB) e descompacte-o.
2. Envie **o conteúdo da pasta `eclair`** para a pasta pública da sua hospedagem (`public_html`, `www`…) com o seu programa de FTP (FileZilla…) ou pelo gerenciador de arquivos da hospedagem.
   > ⚠️ Os arquivos `.htaccess` são ocultos: ative “Mostrar arquivos ocultos” para ter certeza de enviá-los.
3. Abra o endereço do seu site: **o assistente de instalação começa** (em 7 idiomas, incluindo português).
4. Responda a 4 perguntas (seu site, sua conta, sua visibilidade, o endereço do seu painel). Confete 🎉: seu site está no ar, com páginas e um artigo de exemplo.

> 🔒 Por segurança, a instalação precisa ser feita **nas 2 horas** seguintes à primeira visita (senão qualquer pessoa poderia se apoderar de um site enviado e esquecido). Depois desse prazo, apague o arquivo `data/install-window.php` por FTP e recarregue a página.

Instalação em uma subpasta (`exemplo.com.br/site/`): basta enviar os arquivos para essa subpasta, tudo é detectado automaticamente.

### Os 4 tipos de site

| Tipo (no assistente) | O que é criado |
|---|---|
| 🏢 Site institucional | Página inicial (destaque, diferenciais, últimos artigos), Serviços (com FAQ), Sobre, Contato, Blog, aviso legal, política de privacidade |
| ✍️ Blog | Página inicial = últimos artigos, Sobre, Contato, categoria “Guides”, páginas legais |
| 🧑‍💻 Freelancer / portfólio | Página inicial com o seu nome, habilidades, Sobre, Blog, Contato, páginas legais |
| 🎯 Página única | Uma página de vendas (destaque, diferenciais, FAQ, formulário) + páginas legais, sem blog |

Os conteúdos iniciais existem em francês, espanhol e inglês: um site em português começa com os textos em inglês, para você traduzir. As páginas legais trazem uma estrutura baseada na legislação francesa (LCEN), espanhola (LSSI) e no RGPD europeu; as exigências do seu país (por exemplo, a LGPD no Brasil) ficam por sua conta.

### Logo após a instalação

O painel mostra uma checklist “Primeiros passos”: logo e cor, página inicial, primeiro artigo, perfil de autor, autenticação em dois fatores, aviso legal, Google Search Console.

---

## Recursos em detalhe

### ⚡ Velocidade

- **Cache estático automático**: cada página é gerada uma vez e depois servida sem carregar a aplicação. O cache se limpa sozinho a cada alteração (e no horário previsto para um artigo agendado).
- **CSS embutido na página** (≈ 12 KB minificado), fontes do sistema: nenhuma requisição bloqueante, nenhum deslocamento de layout.
- **Imagens otimizadas no envio**: redimensionadas (no máximo 2000 px), disponíveis em **WebP responsivo** (480, 800, 1200, 1600 px), dimensões explícitas, carregamento lento e prioridade para a imagem principal.
- ETag e respostas `304 Not Modified`, compressão Brotli/Gzip e cache do navegador de um ano para os arquivos estáticos (via `.htaccess`).

### 🔎 SEO

- URLs limpas, **redirecionamento 301 automático** quando um endereço muda, gerenciador de redirecionamentos (prático para migrar um site antigo em WordPress ou Wix).
- Título, meta description e **prévia do Google ao vivo** no editor, com contadores.
- **Análise de SEO em tempo real** com pontuação: tamanho do título e da descrição, frase-chave (título, URL, introdução, subtítulos, densidade), número de palavras, estrutura, textos alternativos, links internos, imagem destacada…
- **Dados estruturados JSON-LD** completos: WebSite (+ busca), Organization / Person / LocalBusiness (endereço, horários, perfis sociais), WebPage / AboutPage / ContactPage, BlogPosting (autor, datas, seção), BreadcrumbList, FAQPage, ProfilePage.
- Sitemap XML com imagens e `hreflang`, robots.txt, feed RSS completo, Open Graph e Twitter Cards (**imagem de compartilhamento de 1200×630 gerada automaticamente**), breadcrumbs, URLs canônicas.
- Um único endereço por conteúdo: redirecionamentos para HTTPS, com/sem www, barra final, maiúsculas, `index.php`.
- **IndexNow**: Bing, Yandex, Seznam… são avisados a cada publicação.
- Verificação do Google Search Console e do Bing Webmaster Tools (cole a tag `<meta>` e o Éclair extrai o código).
- Multilíngue: idioma por conteúdo, ligação entre traduções e tags `hreflang` automáticas.
- Tipografia francesa automática para conteúdos em francês (espaços não separáveis antes de `: ; ! ?` e dentro das aspas « »).

### 🤖 GEO (motores de resposta com IA)

- **`/llms.txt`** (padrão llmstxt.org) e **`/llms-full.txt`** gerados automaticamente.
- **Versão Markdown de cada página**: adicione `.md` a qualquer URL (com cabeçalho de metadados, `noindex` e URL canônica para não criar conteúdo duplicado).
- **Regras para robôs de IA em um clique**: aberto (recomendado para ser citado), só citações (motores de resposta permitidos, treinamento recusado) ou fechado. Cobre GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Meta-ExternalAgent… Diretiva `Content-Signal` opcional.
- Bloco **“O essencial”**: o resumo que as IAs gostam de citar, exibido no início do artigo.
- **Pontuação GEO** no editor: resumo, introdução direta, FAQ, subtítulos em forma de pergunta, listas e tabelas, números concretos, fontes externas, autor identificado (E-E-A-T), parágrafos curtos, atualidade.
- Páginas de autor com biografia e perfis (`sameAs`), datas de publicação e de atualização visíveis.

### ✍️ Editor de blocos

- 16 blocos: texto, subtítulo (H2-H4), lista, citação, imagem, quadro de destaque, FAQ, tabela, botão, vídeo do YouTube/Vimeo (sem cookies antes do clique), código, separador, destaque, diferenciais, últimos artigos, formulário de contato.
- Digite **`/`** em uma linha vazia para escolher um bloco, arraste e solte, barra de formatação (negrito, itálico, link, código, marca-texto).
- **Links internos assistidos**: enquanto você digita um link, o editor sugere suas páginas e artigos (links internos em 2 segundos).
- **Colagem inteligente** do Word, do Google Docs ou de uma página web: títulos, listas, tabelas e negritos viram blocos limpos.
- Salvamento com `Ctrl+S`, rascunho local de segurança (nada se perde se a conexão cair), prévia real antes de publicar, **histórico das últimas 20 versões**, publicação agendada.

### 🧩 Todo o resto

Biblioteca de mídia (arrastar e soltar, textos alternativos, detecção das imagens em uso), menus com arrastar e soltar (cabeçalho e rodapé), categorias “pilar” para um cluster de conteúdo, busca no site, formulário de contato antispam **sem captcha** (campo isca, token, limites por rede e por dia) com caixa de entrada, contas de administrador e redator, modo escuro automático, 6 fontes do sistema, cor principal com contraste WCAG garantido, painel em 7 idiomas (português, inglês, francês, espanhol, alemão, italiano, chinês), site público em 6 idiomas (fr, es, en, de, it, pt).

---

## Uso no dia a dia

| Quero… | Onde |
|---|---|
| Escrever um artigo | Painel › **Escrever um artigo** |
| Editar uma página a partir do site | Botão **✏️ Editar** (visível só quando você está logado) |
| Mudar logo, cor, fontes | Configurações › Aparência |
| Escolher a página inicial | Configurações › Geral |
| Permitir ou bloquear as IAs | Configurações › IA & GEO |
| Conectar o Google Search Console | Configurações › SEO |
| Adicionar um link ao menu | Menus |
| Redirecionar uma URL antiga | Redirecionamentos |
| Ativar a autenticação em dois fatores | Meu perfil › Autenticação de dois fatores |
| Baixar um backup | Segurança › Backup |

Atalhos do editor: `/` inserir um bloco · `Ctrl+S` salvar · `Ctrl+K` link · `Ctrl+B` negrito · `Ctrl+I` itálico · `Enter` novo parágrafo · `Shift+Enter` quebra de linha.

---

## Segurança

O Éclair foi feito para um ambiente hostil (hospedagem compartilhada, robôs testando senhas o tempo todo). Ele passou por uma auditoria de segurança ofensiva (condições de corrida, força bruta, redirecionamentos, injeções, arquivos maliciosos): cada falha encontrada foi corrigida e o ataque foi repetido para comprovar (`tests/run.php`).

**Dados e servidor**
- **Sem banco de dados**: nenhuma injeção de SQL é possível.
- **Dados com dupla proteção**: as pastas `data/`, `app/` e `cache/` são bloqueadas pelo `.htaccess`; cada arquivo de dados começa com `<?php exit; ?>` (mesmo que um servidor ignorasse os `.htaccess`, nada seria exibido) e nenhuma sequência `<?php` pode ser gravada nele. Arquivos privados (permissões 0600), gravações atômicas.
- **Instalação protegida**: ela só é possível nas 2 horas seguintes à primeira visita, e o endereço do site só é usado para redirecionar os visitantes depois de ser **verificado pelo próprio servidor** (um endereço errado ou adulterado não desvia nada).

**Login**
- **Senhas** protegidas com Argon2id (19 MiB, 2 passagens: seguro e viável em hospedagem compartilhada; bcrypt como alternativa), no mínimo 10 caracteres, senhas comuns recusadas.
- **Proteção contra força bruta**: cada tentativa é reservada antes de ser verificada (impossível passar do limite com requisições em paralelo); limite por rede (endereço IPv4 ou bloco /64 em IPv6) e por conta visada. **Seu dispositivo de costume nunca é bloqueado** por um atacante que erra de propósito. No máximo duas verificações de senha ao mesmo tempo: uma enxurrada de requisições não esgota a memória.
- **Autenticação em dois fatores TOTP** (Google Authenticator, 1Password, Bitwarden…) com QR code e 8 códigos de recuperação exibidos uma única vez; cada código só vale uma vez, mesmo enviado em paralelo; depois de 10 códigos errados, a 2FA da conta fica bloqueada por 15 minutos e o titular é avisado por e-mail.
- **Contas alteradas de forma atômica**: um login em andamento nunca consegue desfazer uma troca de senha, uma desconexão geral, a ativação da 2FA, um rebaixamento ou uma exclusão.
- **Senha solicitada de novo** nas ações sensíveis (backup, gestão de contas, configurações avançadas, troca de e-mail) depois de 15 minutos do login. As senhas provisórias precisam ser trocadas no primeiro login.
- **Sessões reforçadas**: nenhuma sessão antes do login (os robôs não enchem o disco), cookies `HttpOnly`, `Secure`, `SameSite=Strict`, regeneradas no login, desconexão por inatividade, vinculadas ao navegador, “Desconectar meus outros dispositivos”. O painel é forçado para HTTPS assim que o site usa HTTPS.
- **Tokens CSRF** em todas as ações e verificação da origem das requisições.

**Site público**
- **Cabeçalhos rígidos**: Content-Security-Policy (nenhum script embutido, hash SHA-256 para o estilo), proteção contra clickjacking, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS opcional. Eles dependem das configurações, nunca da requisição: o cache não pode ser envenenado.
- **Nenhum redirecionamento aberto**: endereços adulterados (`/\exemplo.com`, caracteres de controle…) retornam 404.
- **Conteúdo higienizado** por lista de permissões (o HTML é analisado e depois totalmente reescrito); links `javascript:` e `data:` são impossíveis, mesmo disfarçados (`javascript&colon;`). As versões Markdown, `llms.txt` e RSS continuam inertes.
- **Formulário de contato** sem captcha: campo isca, token, 5 mensagens por rede a cada 10 minutos e 100 por dia para o site inteiro; caixa limitada a 500 mensagens (o spam sai primeiro), com paginação.
- **Imagens recodificadas no envio** (remove cargas ocultas e dados EXIF/GPS); GIFs animados reescritos bloco a bloco; qualquer imagem que contenha código é recusada; no máximo 40 megapixels; SVGs recusados, nenhum script nem listagem de arquivos em `/media`.

**Painel**
- **Endereço do painel personalizável** (menos robôs), papéis de administrador / redator; o botão “Editar” do site não revela esse endereço.
- **Registros limitados** (campos truncados, no máximo 1 MB): impossível inflá-los.
- **Verificação de integridade**: compara cada arquivo com sua impressão digital original e aponta arquivos suspeitos, inclusive um `.php` sem a linha de proteção em `data/` ou `cache/`.
- **Teste de proteção ao vivo**: a página Segurança verifica, a partir do seu navegador, se os arquivos internos estão mesmo inacessíveis e se o endereço do site está verificado.

---

## Atualizar, fazer backup, mudar de hospedagem

**Fazer backup**: Segurança › Baixar backup (arquivo ZIP de `data/` e `media/`). Sem a extensão zip, copie essas duas pastas por FTP.

**Atualizar o Éclair**:
1. Faça um backup.
2. Substitua por FTP `index.php`, `.htaccess` e as pastas `app/` e `assets/` pelos da nova versão.
3. **Não mexa** em `data/` (conteúdos, configurações, contas) nem em `media/` (imagens).
4. No painel: Configurações › Avançado › Limpar cache.

**Mudar de hospedagem ou de domínio**: copie a pasta inteira e depois troque o endereço em Configurações › Geral. Os links internos se adaptam automaticamente, inclusive em uma subpasta.

---

## Solução de problemas

**Esqueceu a senha (ou perdeu o celular da 2FA) e não há outro administrador?**
Crie por FTP um arquivo `data/reset.php` contendo exatamente estas três linhas:

```
<?php exit; ?>
seu-endereco@exemplo.com.br
SuaNovaSenha2026
```

Em até 24 horas, **faça login com esse e-mail e essa nova senha**: a senha é substituída, a autenticação em dois fatores dessa conta é desativada, suas outras sessões são encerradas e o arquivo é apagado automaticamente. Ninguém mais consegue acionar isso (é preciso conhecer o conteúdo do arquivo); um arquivo sem a primeira linha é recusado e apagado.

**“Instalação bloqueada”**: a janela de 2 horas após a primeira visita já passou. Apague `data/install-window.php` por FTP e recarregue a página.

**Endereço do site não verificado (página Segurança)**: seu servidor não consegue chamar a si mesmo no endereço do site (firewall da hospedagem, DNS ainda não propagado…). O site funciona normalmente, mas sem redirecionamento para o endereço oficial (com/sem www). Clique em “Verificar endereço do site” quando o domínio estiver pronto.

**Erro 404 em todas as páginas, menos na inicial**: o arquivo `.htaccess` não foi enviado (arquivo oculto) ou a reescrita de URL está desativada. Se a sua hospedagem exigir, tire o comentário da linha `RewriteBase /` do `.htaccess`.

**Erro 500 logo depois do envio**: sua hospedagem recusa uma diretiva do `.htaccess` (muito raro: só são usadas diretivas aceitas em todo lugar). Remova o bloco em questão ou fale com a sua hospedagem.

**As imagens não são convertidas em WebP**: falta a extensão GD (ou o suporte a WebP dela). Ative-a no painel de PHP da sua hospedagem; enquanto isso, as imagens continuam em JPG/PNG.

**Não recebo os e-mails do formulário**: as mensagens também ficam salvas no painel (Mensagens). Confira o endereço em Configurações › Contato; algumas hospedagens exigem que o remetente pertença ao domínio.

**Modo de depuração**: crie um arquivo `eclair-config.php` na raiz (veja `eclair-config.exemple.php`) com `define('ECLAIR_DEBUG', true);` e apague-o quando o problema estiver resolvido.

### Nginx

No Nginx, o Éclair não precisa de `.htaccess`, mas é preciso reproduzir estas regras:

```nginx
location ~ ^/(app|data|cache)(/|$) { return 404; }
location ~ /\.(?!well-known/) { return 404; }
location ^~ /media/ {
    location ~* \.(php\d?|phtml|phar|s?html?|svgz?|xml|js)$ { return 404; }
    add_header X-Content-Type-Options nosniff;
    expires 1y;
}
location ~* \.(webp|avif|jpe?g|png|gif|ico|css|js|woff2)$ { expires 1y; add_header Cache-Control "public, immutable"; }
location / { try_files $uri /index.php$is_args$args; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { return 404; }
```

---

## Para desenvolvedores

```
eclair/                  ← o que vai para a hospedagem
├── index.php            front controller (serve o cache sem carregar a aplicação)
├── .htaccess            reescrita, proteções, compressão, cache do navegador
├── app/lib/             código (classes Eclair\…): Content, Blocks, Seo, Analyzer, Media, Auth…
├── app/admin/views/     telas do painel
├── app/theme/           templates do site público + theme.css (inserido em cada página)
├── app/lang/            traduções do painel (en, es); o texto em francês serve de chave
├── assets/admin/        CSS e JavaScript do painel (sem dependências)
├── assets/site/site.js  o único script público (2,4 KB, 1,2 KB comprimido)
├── data/                conteúdos, configurações, contas (criado na instalação, nunca público)
├── cache/               páginas geradas
└── media/               imagens enviadas
tests/run.php            testes unitários + integração HTTP (incluindo os ataques da auditoria de segurança)
tools/router.php         roteador para o servidor embutido do PHP (desenvolvimento)
tools/build.php          impressões de integridade + arquivo dist/eclair-cms-<versão>.zip
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # servidor de desenvolvimento
php tests/run.php                                  # testes (unitários + HTTP)
php tools/build.php                                # arquivo pronto para distribuir
```

`PHP_CLI_SERVER_WORKERS` permite que o site chame a si mesmo (verificação do endereço): com um único processo, a verificação simplesmente falha e os redirecionamentos para o endereço oficial continuam desativados.

O tema é personalizado editando `app/theme/*.php` e `app/theme/theme.css` (guarde uma cópia: a verificação de integridade vai apontar esses arquivos como modificados, é normal).

---

O Éclair é distribuído sob a [licença MIT](../LICENSE). Parte dos ícones do painel é derivada do [Lucide](https://lucide.dev) (licença ISC): veja [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
