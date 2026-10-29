<p align="center">
  <a href="GUIDE.md">Français</a> · <a href="GUIDE.en.md">English</a> · <b>Español</b> · <a href="GUIDE.de.md">Deutsch</a> · <a href="GUIDE.it.md">Italiano</a> · <a href="GUIDE.pt-BR.md">Português</a> · <a href="GUIDE.zh-CN.md">简体中文</a>
</p>

# Guía de Éclair CMS

Todo lo que necesitas saber para instalar, usar, proteger y mantener Éclair. Para la presentación, vuelve al [README](../README.es.md).

## Índice

1. [Instalación](#instalación)
2. [Funciones en detalle](#funciones-en-detalle)
3. [Uso diario](#uso-diario)
4. [Seguridad](#seguridad)
5. [Actualizar, hacer copias de seguridad, mudarse](#actualizar-hacer-copias-de-seguridad-mudarse)
6. [Solución de problemas](#solución-de-problemas) (incluido [Nginx](#nginx))
7. [Para desarrolladores](#para-desarrolladores)

---

## Instalación

### Requisitos

- **PHP 8.1 o superior** (se recomienda 8.3), con las extensiones `mbstring`, `json`, `dom` y `session` (activadas por defecto casi en todas partes).
- Recomendado: `gd` (imágenes WebP), `zip` (copias de seguridad en un clic), `curl` (IndexNow).
- Un servidor **Apache o LiteSpeed** (casi todos los hostings compartidos: Hostinger, IONOS, OVHcloud…). Para Nginx, consulta [más abajo](#nginx).
- Un certificado HTTPS (gratuito con Let's Encrypt en casi todos los proveedores).

El asistente lo comprueba todo por ti y te explica qué corregir.

### Pasos

1. Descarga el archivo [`eclair-cms-1.0.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.0.0.zip) (257 KB) y descomprímelo.
2. Sube **el contenido de la carpeta `eclair`** a la carpeta pública de tu hosting (`public_html`, `www`…) con tu programa FTP (FileZilla…) o con el gestor de archivos de tu proveedor.
   > ⚠️ Los archivos `.htaccess` están ocultos: activa «Mostrar archivos ocultos» para asegurarte de subirlos.
3. Abre la dirección de tu sitio: **arranca el asistente de instalación** (en español, francés o inglés).
4. Responde 4 preguntas (tu sitio, tu cuenta, tu visibilidad, la dirección de tu panel). Confeti 🎉: tu sitio está en línea, con páginas y un artículo de ejemplo.

> 🔒 Por seguridad, la instalación debe hacerse **en las 2 horas** siguientes a la primera visita (si no, cualquiera podría apropiarse de un sitio subido y olvidado). Pasado ese plazo, borra el archivo `data/install-window.php` por FTP y recarga la página.

Instalación en una subcarpeta (`ejemplo.es/sitio/`): basta con subir los archivos a esa subcarpeta, todo se detecta automáticamente.

### Los 4 tipos de sitio

| Tipo | Lo que se crea |
|---|---|
| 🏢 Web corporativa | Inicio (portada, ventajas, últimos artículos), Servicios (con FAQ), Sobre nosotros, Contacto, Blog, aviso legal, política de privacidad |
| ✍️ Blog | Inicio = últimos artículos, Sobre nosotros, Contacto, categoría «Guías», páginas legales |
| 🧑‍💻 Freelance / portfolio | Inicio con tu nombre, habilidades, Sobre nosotros, Blog, Contacto, páginas legales |
| 🎯 Página única | Una página de venta (portada, ventajas, FAQ, formulario) + páginas legales, sin blog |

Los contenidos iniciales existen en español, francés e inglés. Las páginas legales incluyen un esqueleto que completar (LSSI en España, LCEN en Francia, RGPD).

### Justo después de la instalación

El panel muestra una lista de «Primeros pasos»: logo y color, página de inicio, primer artículo, perfil de autor, verificación en dos pasos, aviso legal, Google Search Console.

---

## Funciones en detalle

### ⚡ Velocidad

- **Caché estática automática**: cada página se genera una vez y después se sirve sin cargar la aplicación. La caché se vacía sola con cada cambio (y a la hora prevista para un artículo programado).
- **CSS integrado en la página** (≈ 12 KB minificado), fuentes del sistema: ninguna petición bloqueante, ningún salto de diseño.
- **Imágenes optimizadas al subirlas**: redimensionadas (2000 px como máximo), disponibles en **WebP adaptable** (480, 800, 1200, 1600 px), dimensiones explícitas, carga diferida y prioridad para la imagen principal.
- ETag y respuestas `304 Not Modified`, compresión Brotli/Gzip y caché del navegador de un año para los archivos estáticos (mediante `.htaccess`).

### 🔎 SEO

- URL limpias, **redirección 301 automática** cuando cambia una dirección, gestor de redirecciones (práctico para migrar una web antigua en WordPress o Wix).
- Título, metadescripción y **vista previa de Google en directo** en el editor, con contadores.
- **Análisis SEO en tiempo real** con puntuación: longitud del título y de la descripción, frase clave (título, URL, introducción, subtítulos, densidad), número de palabras, estructura, textos alternativos, enlazado interno, imagen destacada…
- **Datos estructurados JSON-LD** completos: WebSite (+ búsqueda), Organization / Person / LocalBusiness (dirección, horario, perfiles sociales), WebPage / AboutPage / ContactPage, BlogPosting (autor, fechas, sección), BreadcrumbList, FAQPage, ProfilePage.
- Sitemap XML con imágenes y `hreflang`, robots.txt, feed RSS completo, Open Graph y Twitter Cards (**imagen para compartir de 1200×630 generada automáticamente**), migas de pan, URL canónicas.
- Una sola dirección por contenido: redirecciones de HTTPS, con o sin www, barra final, mayúsculas, `index.php`.
- **IndexNow**: Bing, Yandex, Seznam… reciben un aviso en cada publicación.
- Verificación de Google Search Console y Bing Webmaster Tools (pega la etiqueta `<meta>` y Éclair extrae el código).
- Multilingüe: idioma por contenido, enlace entre traducciones y etiquetas `hreflang` automáticas.
- Tipografía francesa automática para los contenidos en francés (espacios de no separación antes de `: ; ! ?` y dentro de las comillas « »).

### 🤖 GEO (motores de respuesta IA)

- **`/llms.txt`** (estándar llmstxt.org) y **`/llms-full.txt`** generados automáticamente.
- **Versión Markdown de cada página**: añade `.md` a cualquier URL (con cabecera de metadatos, `noindex` y URL canónica para no crear contenido duplicado).
- **Política de robots de IA en un clic**: abierto (recomendado para que te citen), solo citas (motores de respuesta permitidos, entrenamiento rechazado) o cerrado. Gestiona GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Google-Extended, Applebot-Extended, CCBot, Meta-ExternalAgent… Directiva `Content-Signal` opcional.
- Bloque **«Lo esencial»**: el resumen que las IA citan con gusto, mostrado al principio del artículo.
- **Puntuación GEO** en el editor: resumen, introducción directa, FAQ, subtítulos en forma de pregunta, listas y tablas, cifras concretas, fuentes externas, autor identificado (E-E-A-T), párrafos cortos, actualidad.
- Páginas de autor con biografía y perfiles (`sameAs`), fechas de publicación y de actualización visibles.

### ✍️ Editor de bloques

- 16 bloques: texto, subtítulo (H2-H4), lista, cita, imagen, recuadro, FAQ, tabla, botón, vídeo de YouTube/Vimeo (sin cookies antes del clic), código, separador, portada, ventajas, últimos artículos, formulario de contacto.
- Escribe **`/`** en una línea vacía para elegir un bloque, arrastrar y soltar, barra de formato (negrita, cursiva, enlace, código, resaltado).
- **Enlaces internos asistidos**: mientras escribes un enlace, el editor te propone tus páginas y artículos (enlazado interno en 2 segundos).
- **Pegado inteligente** desde Word, Google Docs o una página web: títulos, listas, tablas y negritas se convierten en bloques limpios.
- Guardado con `Ctrl+S`, borrador local de respaldo (no se pierde nada si se corta la conexión), vista previa real antes de publicar, **historial de las 20 últimas versiones**, publicación programada.

### 🧩 Todo lo demás

Biblioteca de medios (arrastrar y soltar, textos alternativos, detección de las imágenes en uso), menús con arrastrar y soltar (cabecera y pie de página), categorías «pilar» para un clúster temático, buscador interno, formulario de contacto antispam **sin captcha** (campo trampa, token, límites por red y por día) con bandeja de entrada, cuentas de administrador y redactor, modo oscuro automático, 6 fuentes del sistema, color principal con contraste WCAG garantizado, panel en español, francés e inglés, sitio público en 6 idiomas (fr, es, en, de, it, pt).

---

## Uso diario

| Quiero… | Dónde |
|---|---|
| Escribir un artículo | Panel › **Escribir un artículo** |
| Editar una página desde el sitio | Botón **✏️ Editar** (visible solo cuando has iniciado sesión) |
| Cambiar el logo, el color, las fuentes | Ajustes › Apariencia |
| Elegir la página de inicio | Ajustes › General |
| Permitir o bloquear las IA | Ajustes › IA & GEO |
| Conectar Google Search Console | Ajustes › SEO |
| Añadir un enlace al menú | Menús |
| Redirigir una URL antigua | Redirecciones |
| Activar la verificación en dos pasos | Mi perfil › Verificación en dos pasos |
| Descargar una copia de seguridad | Seguridad › Copia de seguridad |

Atajos del editor: `/` insertar un bloque · `Ctrl+S` guardar · `Ctrl+K` enlace · `Ctrl+B` negrita · `Ctrl+I` cursiva · `Intro` nuevo párrafo · `Mayús+Intro` salto de línea.

---

## Seguridad

Éclair está pensado para un entorno hostil (hosting compartido, robots probando contraseñas sin descanso). Ha pasado una auditoría de seguridad ofensiva (condiciones de carrera, fuerza bruta, redirecciones, inyecciones, archivos trampa): cada vulnerabilidad encontrada se corrigió y después se repitió el ataque para demostrarlo (`tests/run.php`).

**Datos y servidor**
- **Sin base de datos**: no hay inyección SQL posible.
- **Datos con doble protección**: las carpetas `data/`, `app/` y `cache/` están bloqueadas por `.htaccess`; cada archivo de datos empieza por `<?php exit; ?>` (aunque un servidor ignorara los `.htaccess`, no se mostraría nada) y no se puede escribir en él ninguna secuencia `<?php`. Archivos privados (permisos 0600), escrituras atómicas.
- **Instalación protegida**: solo es posible en las 2 horas siguientes a la primera visita, y la dirección del sitio solo se usa para redirigir a los visitantes después de haber sido **verificada por el propio servidor** (una dirección errónea o manipulada no desvía nada).

**Inicio de sesión**
- **Contraseñas** cifradas con Argon2id (19 MiB, 2 pasadas: seguro y llevadero en un hosting compartido; bcrypt como alternativa), 10 caracteres como mínimo, contraseñas habituales rechazadas.
- **Protección contra fuerza bruta**: cada intento se reserva antes de comprobarse (imposible superar el límite con peticiones en paralelo); límite por red (dirección IPv4 o bloque /64 en IPv6) y por cuenta atacada. **Tu dispositivo habitual nunca queda bloqueado** por un atacante que falla a propósito. Como máximo, dos comprobaciones de contraseña a la vez: una avalancha de peticiones no agota la memoria.
- **Verificación en dos pasos TOTP** (Google Authenticator, 1Password, Bitwarden…) con código QR y 8 códigos de recuperación que se muestran una sola vez; cada código solo sirve una vez, aunque se envíe en paralelo; tras 10 códigos erróneos, la verificación en dos pasos de la cuenta se bloquea 15 minutos y se avisa a su titular por correo electrónico.
- **Cuentas modificadas de forma atómica**: un inicio de sesión en curso nunca puede anular un cambio de contraseña, un cierre de sesión general, la activación de la verificación en dos pasos, una bajada de rol o una eliminación.
- **Contraseña solicitada de nuevo** para las acciones sensibles (copia de seguridad, gestión de cuentas, ajustes avanzados, cambio de correo) pasados 15 minutos desde el inicio de sesión. Las contraseñas temporales deben cambiarse en el primer inicio de sesión.
- **Sesiones reforzadas**: ninguna sesión antes de iniciar sesión (los robots no llenan el disco), cookies `HttpOnly`, `Secure`, `SameSite=Strict`, regeneradas al iniciar sesión, cierre tras inactividad, vinculadas al navegador, «Cerrar sesión en mis otros dispositivos». El panel se fuerza a HTTPS en cuanto el sitio lo usa.
- **Tokens CSRF** en todas las acciones y verificación del origen de las peticiones.

**Sitio público**
- **Cabeceras estrictas**: Content-Security-Policy (ningún script en línea, huella SHA-256 para el estilo), protección contra clickjacking, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS opcional. Dependen de los ajustes, nunca de la petición: la caché no puede envenenarse.
- **Ninguna redirección abierta**: las direcciones manipuladas (`/\ejemplo.com`, caracteres de control…) devuelven un 404.
- **Contenido saneado** mediante lista blanca (el HTML se analiza y después se reescribe por completo); los enlaces `javascript:` y `data:` son imposibles, incluso disfrazados (`javascript&colon;`). Las versiones Markdown, `llms.txt` y RSS siguen siendo inertes.
- **Formulario de contacto** sin captcha: campo trampa, token, 5 mensajes por red cada 10 minutos y 100 al día para todo el sitio; bandeja limitada a 500 mensajes (el spam se elimina primero) y paginada.
- **Imágenes recodificadas al subirlas** (elimina las cargas ocultas y los datos EXIF/GPS); GIF animados reescritos bloque a bloque; cualquier imagen que contenga código se rechaza; 40 megapíxeles como máximo; SVG rechazados, ningún script ni listado de archivos en `/media`.

**Panel de administración**
- **Dirección del panel personalizable** (menos robots), roles de administrador y redactor; el botón «Editar» del sitio no revela esa dirección.
- **Registros acotados** (campos truncados, 1 MB como máximo): imposible inflarlos.
- **Control de integridad**: compara cada archivo con su huella original y detecta los archivos sospechosos, incluido un `.php` sin línea de protección en `data/` o `cache/`.
- **Prueba de protección en directo**: la página Seguridad comprueba desde tu navegador que los archivos internos son inaccesibles y que la dirección del sitio está verificada.

---

## Actualizar, hacer copias de seguridad, mudarse

**Hacer una copia de seguridad**: Seguridad › Descargar una copia de seguridad (archivo ZIP de `data/` y `media/`). Sin la extensión zip, copia esas dos carpetas por FTP.

**Actualizar Éclair**:
1. Haz una copia de seguridad.
2. Sustituye por FTP `index.php`, `.htaccess` y las carpetas `app/` y `assets/` por los de la nueva versión.
3. **No toques** `data/` (contenidos, ajustes, cuentas) ni `media/` (imágenes).
4. En el panel: Ajustes › Avanzado › Vaciar la caché.

**Mudarse a otro hosting o dominio**: copia toda la carpeta y después cambia la dirección en Ajustes › General. Los enlaces internos se adaptan automáticamente, también en una subcarpeta.

---

## Solución de problemas

**¿Has olvidado tu contraseña (o perdido el móvil de la verificación en dos pasos) y no hay otro administrador?**
Crea por FTP un archivo `data/reset.php` que contenga exactamente estas tres líneas:

```
<?php exit; ?>
tu-direccion@ejemplo.es
TuNuevaClave2026
```

En las 24 horas siguientes, **inicia sesión con ese correo y esa nueva contraseña**: la contraseña se sustituye, la verificación en dos pasos de esa cuenta se desactiva, tus demás sesiones se cierran y el archivo se borra automáticamente. Nadie más puede activarlo (hay que conocer su contenido); un archivo sin la primera línea se rechaza y se borra.

**«Instalación bloqueada»**: ya ha pasado el plazo de 2 horas desde la primera visita. Borra `data/install-window.php` por FTP y recarga la página.

**Dirección del sitio sin verificar (página Seguridad)**: tu servidor no consigue llamarse a sí mismo en la dirección del sitio (cortafuegos del proveedor, DNS aún sin propagar…). El sitio funciona con normalidad, pero sin redirección a la dirección oficial (con o sin www). Haz clic en «Verificar la dirección del sitio» cuando el dominio esté listo.

**Error 404 en todas las páginas salvo la de inicio**: el archivo `.htaccess` no se ha subido (archivo oculto) o la reescritura de URL está desactivada. Si tu proveedor lo exige, quita el comentario de la línea `RewriteBase /` del `.htaccess`.

**Error 500 justo después de subir los archivos**: tu proveedor rechaza una directiva del `.htaccess` (muy raro: solo se usan directivas admitidas en todas partes). Elimina el bloque en cuestión o contacta con tu proveedor.

**Las imágenes no se convierten a WebP**: falta la extensión GD (o su soporte WebP). Actívala en el panel PHP de tu proveedor; mientras tanto, las imágenes se quedan en JPG/PNG.

**No recibo los correos del formulario**: los mensajes también se guardan en el panel (Mensajes). Revisa la dirección en Ajustes › Contacto; algunos proveedores exigen que el remitente pertenezca al dominio.

**Modo de depuración**: crea un archivo `eclair-config.php` en la raíz (consulta `eclair-config.exemple.php`) con `define('ECLAIR_DEBUG', true);` y bórralo cuando hayas resuelto el problema.

### Nginx

Éclair no necesita `.htaccess` con Nginx, pero hay que reproducir estas reglas:

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

## Para desarrolladores

```
eclair/                  ← lo que se sube al hosting
├── index.php            controlador frontal (sirve la caché sin cargar la aplicación)
├── .htaccess            reescritura, protecciones, compresión, caché del navegador
├── app/lib/             código (clases Eclair\…): Content, Blocks, Seo, Analyzer, Media, Auth…
├── app/admin/views/     pantallas del panel
├── app/theme/           plantillas del sitio público + theme.css (insertado en cada página)
├── app/lang/            traducciones del panel (en, es); el texto francés sirve de clave
├── assets/admin/        CSS y JavaScript del panel (sin dependencias)
├── assets/site/site.js  el único script público (2,4 KB, 1,2 KB comprimido)
├── data/                contenidos, ajustes, cuentas (se crea al instalar, nunca es público)
├── cache/               páginas generadas
└── media/               imágenes subidas
tests/run.php            pruebas unitarias + integración HTTP (incluidos los ataques de la auditoría de seguridad)
tools/router.php         enrutador para el servidor integrado de PHP (desarrollo)
tools/build.php          huellas de integridad + archivo dist/eclair-cms-<versión>.zip
```

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # servidor de desarrollo
php tests/run.php                                  # pruebas (unitarias + HTTP)
php tools/build.php                                # archivo listo para distribuir
```

`PHP_CLI_SERVER_WORKERS` permite que el sitio se llame a sí mismo (verificación de su dirección): con un solo proceso, la verificación simplemente falla y las redirecciones a la dirección oficial siguen desactivadas.

El tema se personaliza modificando `app/theme/*.php` y `app/theme/theme.css` (guarda una copia: el control de integridad marcará esos archivos como modificados, es normal).

---

Éclair se distribuye con [licencia MIT](../LICENSE). Parte de los iconos del panel deriva de [Lucide](https://lucide.dev) (licencia ISC): consulta [`THIRD-PARTY-NOTICES.txt`](../THIRD-PARTY-NOTICES.txt).
