<p align="center">
  <a href="GUIDE.md">Français</a> · <a href="GUIDE.en.md">English</a> · <a href="GUIDE.es.md">Español</a> · <a href="GUIDE.de.md">Deutsch</a> · <a href="GUIDE.it.md">Italiano</a> · <b>Português</b> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Guia do Éclair CMS

Tudo o que você precisa saber para instalar, usar, proteger e manter o Éclair. Para a apresentação, volte ao [README](../README.pt-BR.md).

## Sumário

1. [Instalação](#instalação)
2. [Recursos em detalhe](#recursos-em-detalhe)
3. [Uso no dia a dia](#uso-no-dia-a-dia)
4. [Temas](#temas)
5. [Loja](#loja)
6. [Assistente de IA](#assistente-de-ia)
7. [Segurança](#segurança)
8. [Atualizar, fazer backup, mudar de hospedagem](#atualizar-fazer-backup-mudar-de-hospedagem)
9. [Solução de problemas](#solução-de-problemas) (incluindo [Nginx](#nginx))
10. [Para desenvolvedores](#para-desenvolvedores) (incluindo [Tema personalizado](#tema-personalizado))

---

## Instalação

### Requisitos

- **PHP 8.1 ou superior** (8.3 recomendado), com as extensões `mbstring`, `json`, `dom` e `session` (ativas por padrão em quase todo lugar).
- Recomendado: `gd` (imagens WebP), `zip` (backup em um clique), `curl` (IndexNow).
- Um servidor **Apache ou LiteSpeed** (praticamente todas as hospedagens compartilhadas: Hostinger, IONOS, OVHcloud…). Para Nginx, veja [mais abaixo](#nginx).
- Um certificado HTTPS (gratuito com Let's Encrypt em quase todas as hospedagens).

O assistente verifica tudo isso para você e explica o que corrigir.

### Passo a passo

1. Baixe o arquivo [`eclair-cms-1.3.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.3.0.zip) (416 KB) e descompacte-o.
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
- **CSS embutido na página** (de 17 a 23 KB conforme o tema e a loja, de 5 a 6 KB comprimido), fontes do sistema: nenhuma requisição bloqueante, nenhum deslocamento de layout.
- **Imagens otimizadas no envio**: redimensionadas (no máximo 2000 px), disponíveis em **WebP responsivo** (480, 800, 1200, 1600 px), dimensões explícitas, carregamento lento e prioridade para a imagem principal.
- ETag e respostas `304 Not Modified`, compressão Brotli/Gzip e cache do navegador de um ano para os arquivos estáticos (via `.htaccess`).

### 🔎 SEO

- URLs limpas, **redirecionamento 301 automático** quando um endereço muda, gerenciador de redirecionamentos (prático para migrar um site antigo em WordPress ou Wix).
- Título, meta description e **prévia do Google ao vivo** no editor, com contadores.
- **Análise de SEO em tempo real** com pontuação: tamanho do título e da descrição, frase-chave (título, URL, introdução, subtítulos, densidade), número de palavras, estrutura, textos alternativos, links internos, imagem destacada…
- **Dados estruturados JSON-LD** completos: WebSite (+ busca), Organization / Person / LocalBusiness (endereço, horários, perfis sociais, política de devolução), WebPage / AboutPage / ContactPage, BlogPosting (autor, datas, seção), BreadcrumbList, FAQPage, ProfilePage e, para a loja, Product / ProductGroup / Offer (preço, estoque, entrega).
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

- 18 blocos: texto, subtítulo (H2-H4), lista, citação, imagem, quadro de destaque, FAQ, tabela, botão, vídeo do YouTube/Vimeo (sem cookies antes do clique), código, separador, destaque, diferenciais, equipe (retratos, cargos, apresentações), últimos artigos, produtos (vitrine da loja), formulário de contato.
- Digite **`/`** em uma linha vazia para escolher um bloco, arraste e solte, barra de formatação (negrito, itálico, link, código, marca-texto).
- **Links internos assistidos**: enquanto você digita um link, o editor sugere suas páginas e artigos (links internos em 2 segundos).
- **Colagem inteligente** do Word, do Google Docs ou de uma página web: títulos, listas, tabelas e negritos viram blocos limpos.
- Salvamento com `Ctrl+S`, rascunho local de segurança (nada se perde se a conexão cair), prévia real antes de publicar, **histórico das últimas 20 versões**, publicação agendada.

### 🧩 Todo o resto

Biblioteca de mídia (arrastar e soltar, textos alternativos, detecção das imagens em uso), menus com arrastar e soltar (cabeçalho e rodapé), categorias “pilar” para um cluster de conteúdo, busca no site, formulário de contato antispam **sem captcha** (campo isca, token, limites por rede e por dia) com caixa de entrada, contas de administrador e redator, 6 temas com pré-visualização ao vivo, modo escuro automático, 6 fontes do sistema, cor principal com contraste WCAG garantido, painel em 7 idiomas (português, inglês, francês, espanhol, alemão, italiano, chinês), site público em 6 idiomas (fr, es, en, de, it, pt).

---

## Uso no dia a dia

| Quero… | Onde |
|---|---|
| Escrever um artigo | Painel › **Escrever um artigo** |
| Pedir à IA um artigo, uma página ou uma imagem | Campo **“O que você quer criar hoje?”** do painel ou **✨ Assistente de IA** (`Ctrl+J`) |
| Editar uma página a partir do site | Botão **✏️ Editar** (visível só quando você está logado) |
| Mudar tema, logo, cor, fontes | Configurações › Aparência |
| Abrir a loja | Configurações › Loja |
| Adicionar um produto | Conteúdos › **+ Produto** |
| Escolher a página inicial | Configurações › Geral |
| Permitir ou bloquear as IAs | Configurações › IA & GEO |
| Ativar o assistente de IA | Configurações › Assistente de IA |
| Conectar o Google Search Console | Configurações › SEO |
| Adicionar um link ao menu | Menus |
| Redirecionar uma URL antiga | Redirecionamentos |
| Ativar a autenticação em dois fatores | Meu perfil › Autenticação de dois fatores |
| Baixar um backup | Segurança › Backup |

Atalhos do editor: `/` inserir um bloco · `Ctrl+S` salvar · `Ctrl+K` link · `Ctrl+B` negrito · `Ctrl+I` itálico · `Enter` novo parágrafo · `Shift+Enter` quebra de linha · `Ctrl+J` assistente de IA.

---

## Temas

O Éclair vem com **6 temas**. Escolha um em **Configurações › Aparência**: a **pré-visualização ao vivo** mostra o seu site de verdade (início, blog, um artigo, a loja, um produto) no computador ou no celular, e nada é publicado enquanto você não salvar. Você pode navegar pela pré-visualização: os links continuam dentro dela.

| Tema | Estilo | Para quem |
|---|---|---|
| **Éclair** | Claro, moderno e versátil: o tema original | Todos os sites |
| **Editorial** | Uma revista: tipografia serifada, linhas finas, letra capitular, artigo em destaque | Blogs, veículos de comunicação, autores |
| **Ateliê** | Caloroso e artesanal: tons de creme e terracota, formas suaves | Artesãos, restaurantes, bem-estar |
| **Estúdio** | Portfólio minimalista: preto e branco, títulos grandes, imagens sem moldura | Fotógrafos, designers, agências |
| **Loja** | Pensado para vender: fotos quadradas, cabeçalho fixo, botões marcantes | Lojas, criadores |
| **Contraste** | Legibilidade máxima: texto grande, contrastes fortes, links sublinhados | Serviços públicos, associações, idosos |

Escolher um tema aplica a cor e as fontes recomendadas dele: você pode alterá-las depois. Duas configurações de layout completam o tema: o **cabeçalho** (logo à esquerda e menu à direita, ou tudo centralizado) e as **listas de artigos e produtos** (grade de cards ou lista com a imagem à esquerda).

**Por que é leve**: um tema do Éclair é só uma folha de estilo de 1,5 a 3 KB, somada à folha de estilo base. O HTML não muda de um tema para outro: a velocidade, a acessibilidade, os dados estruturados e o SEO continuam iguais. Só o tema ativo é inserido na página, e o modo escuro automático funciona com todos eles.

**Seu próprio tema**: para ir além (templates, estilos, script), crie uma pasta `theme/` ao lado de `index.php`: os arquivos dela têm prioridade sobre os do Éclair e nenhuma atualização os altera. Veja [Tema personalizado](#tema-personalizado).

---

## Loja

O **modo loja leve** serve para vender algumas dezenas de produtos sem o peso de um e-commerce: sem carrinho, sem contas de clientes, sem banco de dados, nenhum dado bancário no seu servidor. Cada produto tem sua própria página, otimizada para o Google e as IAs, com um botão **Comprar** que leva a um **link de pagamento da Stripe** (Payment Links): a Stripe recebe os pagamentos, envia os recibos e gerencia os reembolsos.

### Abrir a loja

1. **Publique o seu aviso legal** (a instalação já criou uma estrutura para você completar) **e as suas condições gerais de venda**. A loja não abre sem essas páginas. Peça para alguém revisá-las: você responde por elas como vendedor.
2. Em **Configurações › Loja**: escolha essas duas páginas, a moeda, o valor do frete e o prazo de entrega, os países de entrega e o prazo de devolução (na UE, a venda a distância dá ao cliente pelo menos 14 dias para desistir da compra, salvo exceções, como um produto personalizado).
3. Clique em **Criar a página de agradecimento**: a Stripe vai redirecionar seus clientes para ela depois do pagamento.
4. Ative **Loja aberta**, salve e depois adicione a loja ao menu (Menus › Loja).

O catálogo fica no endereço `/loja` (`/boutique`, `/tienda`, `/shop` ou `/negozio`, conforme o idioma do site), que pode ser alterado; se você mudá-lo, os endereços antigos são redirecionados.

### Criar uma página de produto

Conteúdos › **+ Produto**. No topo do editor ficam os campos da página de produto:

- **Preço** e **preço riscado** (opcional: o desconto aparece em porcentagem), **disponibilidade** (em estoque, esgotado, pré-venda).
- **Link de pagamento**: o link da Stripe do produto (`https://buy.stripe.com/…`).
- **Fotos**: a primeira é a principal (Google, redes sociais); tente mostrar pelo menos 3 ângulos.
- **Variantes** (tamanho, cor…): cada uma pode ter seu próprio preço e link. No site, a escolha é feita sem JavaScript.
- **Detalhes para o Google Shopping**: marca, referência, código de barras (GTIN/EAN), condição (novo, usado, recondicionado), produto digital ou serviço (sem entrega).

Logo abaixo, descreva o produto com os blocos de sempre e coloque-o em uma **categoria de produtos** (Categorias › “Ela reúne: Produtos (loja)”). A análise de SEO também verifica o preço, o link de pagamento, o número de fotos e os identificadores. Para destacar produtos em uma página, adicione o bloco **Produtos**.

Com o assistente de IA: anexe uma foto e peça “Crie a página do produto da foto anexada. Preço: € 29,90”. Ele redige um rascunho completo, mas **nunca inventa um preço, um desconto ou um link de pagamento**: o que faltar, você completa.

### Conectar a Stripe (Payment Links)

1. Na Stripe, abra **Payment Links** e crie um link: produto, preço e, se necessário, a coleta do endereço de entrega.
2. Na aba **After the payment**, escolha redirecionar o cliente para a sua página de agradecimento (o endereço dela aparece em Configurações › Loja).
3. Nas configurações da Stripe (**Customer emails**), ative os recibos de pagamentos bem-sucedidos.
4. Copie o link e cole-o na página do produto. O Éclair acrescenta a ele a referência do produto (`client_reference_id`): cada pagamento mostra o que foi comprado.

Cada pedido chega para você na Stripe (e por e-mail, se você ativou essa opção): você prepara e envia, e a Stripe cuida do reembolso, se for preciso. Outro provedor de pagamento também funciona, desde que o link de pagamento dele comece com `https://`.

### O que já vem pronto para o Google e as IAs

- **Dados estruturados** Product e Offer em cada página de produto (ProductGroup para as variantes): preço, moeda, estoque, condição, marca, valor do frete e prazo de entrega, e **política de devolução** no nível da sua empresa. São as informações que o Google exibe nos resultados de produtos.
- **Feed de produtos** `/products.xml` no formato do Google Merchant Center: adicione-o como fonte de produtos no Merchant Center para aparecer gratuitamente na aba Shopping.
- Categorias de produtos como páginas pilar, sitemap com todas as fotos, breadcrumbs.
- Para as IAs: preço e disponibilidade de cada produto no `llms.txt` e na versão Markdown de cada página de produto (adicione `.md` ao endereço dela).

### Quanto custa?

Nada do lado do Éclair. A Stripe não cobra assinatura nem taxa de adesão, e os links de pagamento estão incluídos: você paga só uma taxa por pagamento, por exemplo **1,5% + € 0,25** para um cartão padrão do Espaço Econômico Europeu (2,8% + € 0,25 para um cartão premium, 3,15% + € 0,25 para um cartão de fora do EEE, mais 2% em caso de conversão de moeda). Tarifas da Stripe na França, consultadas em 30 de outubro de 2026 em [stripe.com/fr/pricing](https://stripe.com/fr/pricing); elas podem mudar.

### O que a loja não faz

Não há carrinho com vários produtos, cupons de desconto, contas de clientes nem controle automático de estoque: a ideia é continuar simples. Se você vende centenas de produtos ou precisa desses recursos, um e-commerce completo (Shopify, WooCommerce, PrestaShop) vai atender melhor. Os preços exibidos são os que você digita: mostre-os com impostos incluídos se você vende para consumidores finais.

---

## Assistente de IA

O assistente escreve rascunhos, melhora seus textos e cria imagens **no tom do seu site**. Você dá as instruções em linguagem natural, como faria com um colega: “Escreva um artigo sobre como escolher as cores de uma marca, com uma imagem.” Primeiro ele verifica se já existe algo sobre o assunto no site; depois cria a imagem, redige o rascunho completo (título, O essencial, subtítulos, listas ou tabelas, FAQ, meta description, links internos) e o apresenta a você com as pontuações de SEO e GEO. **Nada é publicado sem o seu clique.**

Ele funciona com o **Claude** (Anthropic) ou o **ChatGPT** (OpenAI), com a sua própria chave de API: sem assinatura do Éclair, você paga direto ao provedor, pelo uso real.

### Ativar o assistente (5 minutos)

1. Crie uma chave de API no provedor escolhido: [console.anthropic.com](https://console.anthropic.com/settings/keys) para o Claude, [platform.openai.com](https://platform.openai.com/api-keys) para o ChatGPT, e adicione alguns dólares de crédito.
2. Em **Configurações › Assistente de IA**, escolha o provedor, cole a chave, escolha o modelo e clique em **Testar a conexão** (é gratuito: nenhum texto é gerado).
3. Para as imagens, escolha um gerador: **GPT Image 2.5** (a mesma chave do ChatGPT), **FLUX 3** ([dashboard.bfl.ai](https://dashboard.bfl.ai)) ou **Ideogram 3** ([ideogram.ai](https://ideogram.ai/manage-api), ideal para imagens com texto). Sem gerador, o assistente usa a sua biblioteca de mídia e as fotos que você anexar.
4. Clique em **Redigir meu perfil a partir do meu site**, revise, ajuste e salve.

A partir daí, o assistente aparece no menu (**✨ Assistente de IA**, `Ctrl+J`), no editor e no painel. Os redatores também podem usá-lo, se você permitir.

### O perfil editorial

É o que faz a IA escrever **como você**: quem você é e o que oferece, para quem você escreve, tom e estilo (tratamento formal ou informal, tamanho das frases, humor), valores, regras a seguir (“sempre um link para a página de contato”, “nunca falar de preços”) e estilo das imagens. Ele é redigido em um clique a partir das suas páginas e artigos; a palavra final é sua. A cada pedido, o assistente também segue as regras de análise do Éclair: os rascunhos já miram uma pontuação alta de SEO e GEO desde o início.

### O que você pode pedir

- “Escreva um artigo sobre a manutenção de uma bicicleta elétrica na primavera, com uma imagem.”
- “Adicione Léa Martin, diretora de arte, à página da equipe com a foto anexada.” (anexe a foto pelo 📎, arrastando e soltando ou colando)
- “Crie uma página Nossos serviços que apresente nossos três pacotes.”
- “Sugira 5 ideias de artigos para atrair novos clientes.”
- “Revise minha página inicial e sugira melhorias.”
- No editor: “Adicione um FAQ a este artigo”, “Deixe a introdução mais impactante”.

Cada ação gera um **card** abaixo da resposta:

| Card | O que aconteceu | Seus botões |
|---|---|---|
| 📝 Rascunho | Novo artigo ou nova página, nunca publicado | Abrir · Visualizar · Publicar agora ou agendar |
| ✏️ Alteração proposta | Para um conteúdo **publicado**: nada muda até você aprovar | Visualizar · Aplicar · Ignorar e, se precisar, Cancelar |
| ✅ Atualizado | Um **rascunho** alterado diretamente | Abrir · Visualizar · Cancelar |
| ✅ No editor | O conteúdo aberto é alterado no editor | Cancelar (depois você salva) |
| 🖼️ Imagem | Imagem criada, otimizada (WebP) e guardada na biblioteca de mídia | Imagem destacada (no editor) |

“Cancelar” funciona enquanto ninguém tiver mexido no conteúdo desde então; caso contrário, as versões anteriores continuam no **histórico de versões** do editor. O histórico do assistente guarda as suas 30 últimas conversas. O assistente nunca inventa fatos sobre o seu negócio (preços, horários, números, depoimentos): quando falta uma informação, ele deixa uma marcação visível, como **[preço a confirmar]**, e avisa você.

### Retoques no editor

- **Selecione um trecho** e clique em ✨ na barra de formatação: melhorar, corrigir erros, encurtar, desenvolver, simplificar, mais caloroso, mais profissional, traduzir. Um clique em “Cancelar” restaura o texto.
- **O essencial**: botão “Redigir”.
- **Aba SEO**: título SEO e descrição, frase-chave, ideias de títulos.
- **Aba Análise**: adicionar um FAQ, textos alternativos que faltam, “Melhorar com o assistente”.
- **Imagem destacada**: “Criar com IA” (artigos).
- **Biblioteca de mídia**: “Descrever com IA” escreve o texto alternativo de uma imagem olhando para ela.

### Quanto custa?

Você paga ao provedor conforme o uso real. O Éclair calcula cada gasto a partir dos tokens cobrados e mostra esses gastos em Configurações › Assistente de IA. Estimativa para um artigo completo (cerca de 25.000 tokens lidos e 7.000 escritos, incluindo pesquisas e verificações):

| Modelo | Preço por 1 milhão de tokens (lidos / escritos) | Artigo completo |
|---|---|---|
| Claude Opus 5.5 (o mais capaz) | US$ 4 / US$ 20 | ≈ US$ 0,24 |
| Claude Sonnet 5.5 | US$ 2 / US$ 10 | ≈ US$ 0,12 |
| Claude Haiku 5.5 | US$ 0,10 / US$ 0,50 | < US$ 0,01 |
| GPT-6 Astra | US$ 10 / US$ 50 | ≈ US$ 0,60 |
| GPT-6.1 Sol | US$ 2 / US$ 10 | ≈ US$ 0,12 |
| GPT-6.1 Terra | US$ 1 / US$ 5 | ≈ US$ 0,06 |
| GPT-6 Luna | US$ 0,10 / US$ 0,50 | < US$ 0,01 |

Uma imagem custa cerca de US$ 0,05 (FLUX 3 em 1 megapixel: US$ 0,048). Preços públicos consultados em 29 de outubro de 2026 nas páginas oficiais da [Anthropic](https://platform.claude.com/docs/en/about-claude/pricing), da [OpenAI](https://developers.openai.com/api/docs/pricing) e da [Black Forest Labs](https://docs.bfl.ml/release-notes); eles podem mudar. As instruções fixas do assistente ficam em cache no provedor, o que reduz o custo dos pedidos seguintes.

**Travas de segurança**: orçamento mensal máximo (US$ 10 por padrão), verificado antes de cada etapa de um pedido: quando ele é atingido, o assistente para até o mês seguinte. Além disso, há um limite de solicitações por pessoa e por dia (60 por padrão), e cada pessoa pode ter no máximo dois pedidos em andamento ao mesmo tempo. O orçamento e a cota são definidos em Configurações › Assistente de IA.

### Segurança e privacidade

- **A IA prepara, você decide**: ela pode buscar e ler seus conteúdos, criar rascunhos, alterar um rascunho (dá para desfazer), propor uma alteração em um conteúdo publicado e criar imagens. Ela **não pode publicar, excluir nem mexer nas configurações, nos menus ou nas contas**. Publicar continua sendo um clique humano.
- **Chaves criptografadas** no seu servidor (libsodium ou AES-256-GCM), nunca enviadas ao navegador, nunca exibidas (só aparece um trecho, como “sk-ant…ab12”), **excluídas dos backups**, assim como o histórico de conversas. Para uma proteção reforçada, adicione `define('ECLAIR_SECRET', 'uma-frase-secreta-longa');` em `eclair-config.php` (faça isso antes de salvar suas chaves).
- **O que vai para o provedor**: o seu pedido, as imagens que você anexar, o perfil editorial e o contexto necessário (lista das suas páginas e artigos, conteúdo aberto no editor). Não inclua dados sensíveis. Segundo os termos de cada um (verificados em 29 de outubro de 2026), a [Anthropic](https://www.anthropic.com/legal/commercial-terms) não treina seus modelos com os dados enviados pela API, e a [OpenAI](https://help.openai.com/en/articles/5722486-api-data-usage-policies) também não, por padrão.
- **Conteúdo higienizado**: tudo o que a IA escreve passa pela mesma lista de permissões do editor, e os retoques dela só alteram o texto visível (nunca o endereço de um link). As imagens geradas são baixadas por HTTPS de um endereço público verificado e depois recodificadas como qualquer imagem enviada.
- **Injeção de instruções**: os conteúdos lidos pela IA são tratados como dados, nunca como ordens; e, mesmo que uma página maliciosa tentasse desviá-la, ela só conseguiria preparar um rascunho ou uma proposta, que você veria antes de qualquer efeito.

### Se o assistente não responder

- **“Chave de API recusada”**: a chave foi copiada incompleta ou foi revogada; cole-a de novo e teste a conexão.
- **“Crédito esgotado”**: recarregue sua conta no provedor.
- **“Orçamento de IA do mês esgotado”**: aumente o orçamento em Configurações › Assistente de IA ou espere o mês seguinte.
- **A resposta chega de uma vez, em vez de aparecer aos poucos**: um buffer da sua hospedagem está retendo o fluxo. Tudo funciona, mas sem o efeito “ao vivo”. No Nginx, adicione `fastcgi_buffering off;` no bloco do PHP.
- **“Não foi possível conectar à…”**: sua hospedagem bloqueia as conexões de saída. Peça ao suporte da hospedagem para liberar `api.anthropic.com` ou `api.openai.com` (e o gerador de imagens escolhido).
- **O assistente não aparece**: ele precisa da extensão cURL do PHP e de criptografia (sodium ou openssl), disponíveis em quase todas as hospedagens; o assistente de instalação verifica isso.

---

## Segurança

O Éclair foi feito para um ambiente hostil (hospedagem compartilhada, robôs testando senhas o tempo todo). Ele passou por uma auditoria de segurança ofensiva (condições de corrida, força bruta, redirecionamentos, injeções, arquivos maliciosos): cada falha encontrada foi corrigida e o ataque foi repetido para comprovar (`tests/run.php`).

**Dados e servidor**
- **Sem banco de dados**: nenhuma injeção de SQL é possível.
- **Dados com dupla proteção**: as pastas `data/`, `app/`, `cache/` e `theme/` são bloqueadas pelo `.htaccess`; cada arquivo de dados começa com `<?php exit; ?>` (mesmo que um servidor ignorasse os `.htaccess`, nada seria exibido) e nenhuma sequência `<?php` pode ser gravada nele. Arquivos privados (permissões 0600), gravações atômicas.
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
- **Assistente de IA com limites**: só rascunhos e propostas, chaves de API criptografadas e excluídas dos backups, orçamento mensal e cota diária (detalhes em [Assistente de IA](#segurança-e-privacidade)).
- **Registros limitados** (campos truncados, no máximo 1 MB): impossível inflá-los.
- **Verificação de integridade**: compara cada arquivo com sua impressão digital original e aponta arquivos suspeitos, inclusive um `.php` sem a linha de proteção em `data/`, `cache/` ou no tema personalizado.
- **Teste de proteção ao vivo**: a página Segurança verifica, a partir do seu navegador, se os arquivos internos estão mesmo inacessíveis e se o endereço do site está verificado.

---

## Atualizar, fazer backup, mudar de hospedagem

**Fazer backup**: Segurança › Baixar backup (arquivo ZIP de `data/`, `media/` e do seu tema personalizado `theme/`). Sem a extensão zip, copie essas pastas por FTP.

**Atualizar o Éclair**:
1. Faça um backup.
2. Por FTP, **apague** as pastas `app/` e `assets/` e depois envie as da nova versão, junto com `index.php` e `.htaccess` (apagar antes evita que sobrem arquivos antigos, que a verificação de integridade apontaria).
3. **Não mexa** em `data/` (conteúdos, configurações, contas), em `media/` (imagens) nem em `theme/` ([tema personalizado](#tema-personalizado): nenhuma atualização o modifica).
4. No painel: Configurações › Avançado › Limpar cache.

Você tinha modificado arquivos de `app/theme/` (Éclair 1.3 ou anterior)? **Antes do passo 2**, copie-os para uma pasta `theme/` ao lado de `index.php`: eles são mantidos e têm prioridade sobre os originais.

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
location ~ ^/(app|data|cache|theme)(/|$) { return 404; }
location ~ /\.(?!well-known/) { return 404; }
location ^~ /media/ {
    location ~* \.(php\d?|phtml|phar|s?html?|svgz?|xml|js)$ { return 404; }
    add_header X-Content-Type-Options nosniff;
    expires 1y;
}
location ~* \.(webp|avif|jpe?g|png|gif|ico|css|js|woff2)$ { try_files $uri /index.php$is_args$args; expires 1y; add_header Cache-Control "public, immutable"; }
location / { try_files $uri /index.php$is_args$args; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { return 404; }
```

A primeira regra também protege o [tema personalizado](#tema-personalizado); o `try_files` da regra de arquivos estáticos deixa o Éclair servir os que ele gera (`/favicon.ico`, o script do tema).

---

## Para desenvolvedores

```
eclair/                  ← o que vai para a hospedagem
├── index.php            front controller (serve o cache sem carregar a aplicação)
├── .htaccess            reescrita, proteções, compressão, cache do navegador
├── app/lib/             código (classes Eclair\…): Content, Blocks, Seo, Analyzer, Media, Auth…
├── app/admin/views/     telas do painel
├── app/theme/           templates originais do site público + theme.css (inserido em cada página)
├── app/theme/themes/    os 5 temas opcionais (uma folha CSS cada, somada ao theme.css)
├── app/lang/            traduções do painel (6 idiomas, JSON comprimido); o texto em francês serve de chave
├── assets/admin/        CSS e JavaScript do painel (sem dependências)
├── assets/site/site.js  o único script público (2,4 KB, 1,2 KB comprimido)
├── data/                conteúdos, configurações, contas (criado na instalação, nunca público)
├── cache/               páginas geradas
├── media/               imagens enviadas
└── theme/               seu tema personalizado (opcional): nunca alterado por uma atualização
i18n/                    arquivos-fonte legíveis das traduções (compilados por tools/i18n.php)
tests/run.php            testes unitários + integração HTTP (incluindo os ataques da auditoria de segurança)
tests/fake-ai.php        provedores de IA falsos (Claude, ChatGPT, imagens): o assistente é testado sem chave nem custo
tests/shop.php           temas e loja (páginas de produto, Stripe, JSON-LD, feed do Merchant Center)
tools/router.php         roteador para o servidor embutido do PHP (desenvolvimento)
tools/build.php          impressões de integridade + arquivo dist/eclair-cms-<versão>.zip (PHP compactado)
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # servidor de desenvolvimento
php tests/run.php                                  # 374 verificações unitárias e HTTP
php tools/build.php                                # arquivo pronto para distribuir
```

`PHP_CLI_SERVER_WORKERS` permite que o site chame a si mesmo (verificação do endereço): com um único processo, a verificação simplesmente falha e os redirecionamentos para o endereço oficial continuam desativados.

### Tema personalizado

Não edite `app/theme/`: cada atualização o substitui. Em vez disso, crie uma pasta **`theme/`** ao lado de `index.php` e coloque nela só os arquivos que você muda; para os demais, o Éclair usa os de `app/theme/`.

| Arquivo em `theme/` | Função |
|---|---|
| `layout.php` | estrutura comum: `<head>`, cabeçalho, menu, rodapé |
| `single.php` | página ou artigo |
| `list.php`, `cards.php` | listas (blog, categoria, autor, busca) e cartões de artigos |
| `product.php`, `products.php` | página de produto e cartões da loja |
| `404.php` | página não encontrada |
| `theme.css` | substitui o estilo base; inserido em cada página, precedido das variáveis `--accent`, `--font`, `--font-head`… de Configurações › Aparência |
| `themes/<nome>.css` | substitui a folha de um tema incluído (`editorial`, `atelier`…), somada depois do `theme.css` quando esse tema está escolhido |
| `theme.js` | opcional: script público carregado em todas as páginas |

- **Para começar**, copie para `theme/` o arquivo que você quer mudar e edite a cópia. De preferência, pegue-o no código-fonte comentado no GitHub (`eclair/app/theme/`): no arquivo zip, o PHP é compactado (`php -w`). Como os originais, cada template `.php` começa com `defined('ECLAIR') || exit;` (senão a verificação de integridade o aponta). Seus próprios trechos são chamados com `Theme::partial('nome', $v)` (arquivo `theme/nome.php`).
- **Com os temas incluídos**: se um tema diferente de “Éclair” estiver escolhido em Configurações › Aparência, a folha `themes/<nome>.css` dele é somada depois do seu `theme.css`; substitua-a também (mesmo nome em `theme/themes/`) se ela não combinar com o seu estilo.
- **Atualizações**: `theme/` nunca é substituída e a verificação de integridade continua verde; ali ela só procura intrusos (PHP sem essa linha de proteção, `.phtml`, páginas HTML…). Depois de uma atualização, compare seus templates com os de `app/theme/` para aproveitar as novidades.
- **Proteção**: como `app/`, `theme/` nunca é acessível pela web (`.htaccess`, [regra do Nginx](#nginx)); ela entra nos backups.
- **`theme.js`** é servido pelo Éclair em um endereço como `/_eclair/theme-1a2b3c4d.js`: mesmo site, portanto a CSP o permite (`script-src 'self'`, nenhum script inline); a impressão digital muda a cada edição. Um `layout.php` personalizado o carrega com `<?php if ($script = Theme::script()): ?><script src="<?= e($script) ?>" defer></script><?php endif ?>`.
- **Em outro lugar**: para guardar o tema fora da pasta pública, informe o caminho em `eclair-config.php`: `define('ECLAIR_THEME', '/home/minhaconta/eclair-theme');`. Dentro da pasta pública, só `theme/` é protegida.
- **Depois de cada mudança**, limpe o cache (Configurações › Avançado › Limpar cache); durante o desenvolvimento, o modo de depuração o desativa.

---

O Éclair é distribuído sob a [licença MIT](../LICENSE). Parte dos ícones do painel é derivada do [Lucide](https://lucide.dev) (licença ISC): veja [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
