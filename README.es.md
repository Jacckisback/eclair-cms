<p align="center">
  <a href="README.md">Français</a> · <a href="README.en.md">English</a> · <b>Español</b> · <a href="README.de.md">Deutsch</a> · <a href="README.it.md">Italiano</a> · <a href="README.pt-BR.md">Português</a> · <a href="README.zh-CN.md">简体中文</a>
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/es/banner-dark.webp">
    <img src="docs/images/es/banner-light.webp" alt="Éclair CMS: el CMS ultraligero y ultrarrápido, hecho para Google y la IA" width="100%">
  </picture>
</p>

<p align="center">
  <a href="https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip"><img src="https://img.shields.io/badge/descargar-v1.1.0-5b4cf0?style=for-the-badge" alt="Descargar Éclair 1.1.0"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.1 o superior">
  <img src="https://img.shields.io/badge/Lighthouse-100-0cce6b?style=for-the-badge&logo=lighthouse&logoColor=white" alt="Lighthouse 100">
  <img src="https://img.shields.io/badge/base_de_datos-ninguna-14122b?style=for-the-badge" alt="Sin base de datos">
  <a href="LICENSE"><img src="https://img.shields.io/badge/licencia-MIT-22c55e?style=for-the-badge" alt="Licencia MIT"></a>
</p>

<h3 align="center">Una web profesional en 3 minutos, en cualquier hosting compartido.<br>Sin base de datos. Sin plugins. Sin mantenimiento.</h3>

<p align="center">
  <a href="#instalación-en-3-minutos"><b>Instalar</b></a> ·
  <a href="#por-qué-éclair"><b>Por qué Éclair</b></a> ·
  <a href="#vista-previa"><b>Vista previa</b></a> ·
  <a href="#seguridad"><b>Seguridad</b></a> ·
  <a href="docs/GUIDE.es.md"><b>Guía completa</b></a>
</p>

<br>

<p align="center">
  <img src="docs/images/es/showcase.webp" alt="El editor de Éclair con el análisis SEO y GEO en 100 sobre 100, y el artículo publicado en un móvil" width="100%">
</p>

## Por qué Éclair

WordPress lo hace todo, pero necesita una base de datos, plugins para el SEO, la seguridad y la caché… y actualizaciones sin fin. Éclair reúne lo esencial en un CMS de **menos de 1 MB**, pensado para autónomos, artesanos y pequeñas empresas.

<table>
  <tr>
    <td width="33%" valign="top">
      <h3>⚡ Ultrarrápido</h3>
      Cada página se sirve desde una caché estática, sin cargar la aplicación: <b>100/100</b> en Lighthouse y <b>0,7 ms</b> de tiempo de respuesta del servidor (medido en local).
    </td>
    <td width="33%" valign="top">
      <h3>🔎 SEO integrado</h3>
      Datos estructurados, sitemap, redirecciones, vista previa de Google y <b>puntuación SEO en directo</b> en el editor, sin ningún plugin.
    </td>
    <td width="33%" valign="top">
      <h3>🤖 Listo para la IA</h3>
      <code>llms.txt</code>, versión Markdown de cada página, reglas para 30 robots de IA y <b>puntuación GEO</b> para que ChatGPT, Perplexity o Gemini te citen.
    </td>
  </tr>
  <tr>
    <td width="33%" valign="top">
      <h3>🛡️ Seguro por defecto</h3>
      Verificación en dos pasos, protección contra fuerza bruta, cabeceras estrictas, contenido saneado. <b>Una auditoría de seguridad ofensiva</b>, todas las vulnerabilidades corregidas.
    </td>
    <td width="33%" valign="top">
      <h3>🪶 Ultraligero</h3>
      Sin base de datos: tu contenido se guarda en archivos protegidos. Un archivo de <b>315 KB</b>, cero dependencias, cero cookies en el sitio público.
    </td>
    <td width="33%" valign="top">
      <h3>😊 Facilísimo</h3>
      Instalación guiada en 4 preguntas, editor de bloques con <code>/</code>, lista de primeros pasos. Panel en <b>7 idiomas</b>, sitio en 6 idiomas.
    </td>
  </tr>
</table>

## Éclair frente a WordPress

| | WordPress clásico | ⚡ Éclair |
|---|---|---|
| **Instalación** | Crear una base de datos MySQL y luego lanzar el asistente | ✅ Subir la carpeta y responder 4 preguntas |
| **Base de datos** | MySQL obligatorio | ✅ Ninguna (archivos protegidos) |
| **Archivo de descarga** | ≈ 35 MB[^wp] | ✅ 315 KB |
| **SEO, sitemap, datos estructurados** | Un plugin que instalar y mantener | ✅ Integrado |
| **GEO: llms.txt, Markdown, robots de IA** | Plugins, rara vez completos | ✅ Integrado |
| **Páginas públicas** | Generadas en cada visita, salvo con un plugin de caché | ✅ Servidas desde una caché estática |
| **Cookies en el sitio público** | A menudo muchas | ✅ Ninguna |
| **Verificación en dos pasos, protección contra fuerza bruta** | Plugins | ✅ Integradas |
| **Actualizaciones de plugins** | Constantes, la principal fuente de vulnerabilidades | ✅ Ningún plugin que actualizar |

## Rendimiento

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/es/performance-dark.webp">
  <img src="docs/images/es/performance-light.webp" alt="Lighthouse: 100 en rendimiento, accesibilidad, prácticas recomendadas y SEO. 0,7 ms de tiempo de respuesta, de 7 a 10 KB de HTML comprimido, 1,2 KB de JavaScript, 0 cookies" width="100%">
</picture>

Medido en local en el sitio de demostración (PHP 8.3, página de inicio con imágenes y artículo de 600 palabras), con Lighthouse 12 en móvil y escritorio; una puntuación puede variar un punto de una pasada a otra. Cómo se consigue:

- **Caché estática automática**: cada página se genera una vez y después la sirve directamente `index.php`. La caché se vacía sola con cada cambio.
- **CSS integrado en la página**, fuentes del sistema: ninguna petición bloqueante, ningún salto de diseño.
- **Imágenes optimizadas al subirlas**: redimensionadas, convertidas a **WebP adaptable** (de 480 a 1600 px), dimensiones explícitas y carga diferida.
- **Un único script público**, diferido, de 1,2 KB comprimido. Compresión Brotli o Gzip y caché del navegador de un año mediante `.htaccess`.

En un hosting compartido real, suma la latencia de red y el arranque de PHP de tu proveedor.

## SEO y GEO: encontrado en Google, citado por la IA

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/images/es/geo-dark.webp">
  <img src="docs/images/es/geo-light.webp" alt="Un artículo publicado se convierte en una página HTML con JSON-LD, un sitemap con IndexNow, un feed RSS, llms.txt, una versión Markdown y un robots.txt, leídos por Google, Bing, ChatGPT, Perplexity, Gemini, Claude y Copilot" width="100%">
</picture>

<table>
  <tr>
    <td width="50%" valign="top">
      <h3>🔎 SEO</h3>
      <ul>
        <li><b>Análisis en tiempo real</b> con puntuación: título, metadescripción, frase clave, estructura, enlazado interno, textos alternativos…</li>
        <li><b>Datos estructurados JSON-LD</b>: Organization o LocalBusiness, BlogPosting, FAQPage, BreadcrumbList, autor…</li>
        <li>Sitemap con imágenes y <code>hreflang</code>, feed RSS completo, Open Graph con <b>imagen para compartir generada</b>.</li>
        <li><b>Redirecciones 301 automáticas</b> cuando cambia una URL, y un gestor de redirecciones para migrar una web antigua.</li>
        <li><b>IndexNow</b>: Bing y los demás buscadores compatibles reciben un aviso en cada publicación.</li>
      </ul>
    </td>
    <td width="50%" valign="top">
      <h3>🤖 GEO (motores de respuesta IA)</h3>
      <ul>
        <li><code>/llms.txt</code> y <code>/llms-full.txt</code> generados automáticamente (propuesta <a href="https://llmstxt.org/">llmstxt.org</a>).</li>
        <li><b>Versión Markdown de cada página</b>: añade <code>.md</code> a cualquier URL.</li>
        <li><b>Política de robots de IA en un clic</b>: abierto, solo citas o cerrado, para 30 robots (GPTBot, ClaudeBot, PerplexityBot…).</li>
        <li>Bloque <b>«Lo esencial»</b> al principio de cada artículo: el resumen que las IA citan con gusto.</li>
        <li><b>Puntuación GEO</b>: FAQ, subtítulos en forma de pregunta, cifras concretas, fuentes, autor identificado (E-E-A-T)…</li>
      </ul>
    </td>
  </tr>
</table>

## Vista previa

<table>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/es/frame-dashboard.webp" alt="Panel de Éclair: estadísticas, páginas que mejorar y estado del sitio">
      <p align="center"><b>Panel</b><br><sub>Lo que hay que mejorar y el estado del sitio, de un vistazo.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/es/frame-geo.webp" alt="Ajustes de IA y GEO: elección de la política para los robots de inteligencia artificial">
      <p align="center"><b>Visibilidad en IA</b><br><sub>Permite, limita o bloquea los robots de IA en un clic.</sub></p>
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <img src="docs/images/es/frame-media.webp" alt="Biblioteca de medios de Éclair con las imágenes subidas">
      <p align="center"><b>Biblioteca de medios</b><br><sub>Imágenes redimensionadas y convertidas a WebP automáticamente.</sub></p>
    </td>
    <td width="50%" valign="top">
      <img src="docs/images/frame-dark.webp" alt="Artículo del sitio público en modo oscuro">
      <p align="center"><b>Modo oscuro automático</b><br><sub>El sitio se adapta al tema de cada visitante.</sub></p>
    </td>
  </tr>
</table>

<sub>Capturas de «Atelier Nova», un sitio de demostración ficticio creado con Éclair (ilustraciones originales). El panel aparece en español; el contenido de demostración está en francés.</sub>

## Seguridad

Éclair está pensado para un entorno hostil: hosting compartido y robots probando contraseñas sin descanso.

- **Sin base de datos**, así que sin inyección SQL. Los archivos de datos tienen doble protección (`.htaccess` y una línea `<?php exit; ?>`), son privados y se escriben de forma atómica.
- **Inicio de sesión blindado**: contraseñas con Argon2id, límites por red y por cuenta (un atacante nunca puede bloquear tu dispositivo habitual), **verificación en dos pasos** con códigos de recuperación.
- **Contraseña solicitada de nuevo** para las acciones sensibles, contraseñas temporales que hay que cambiar, sesiones cortas y revocables.
- **Sitio público blindado**: Content-Security-Policy estricta, contenido saneado mediante lista blanca, ninguna redirección abierta, imágenes recodificadas.
- **Instalación protegida**: un plazo de 2 horas, y la URL del sitio verificada por el propio servidor antes de cualquier redirección.
- **Página Seguridad**: control de integridad de los archivos, prueba de protección de las carpetas, registro, copia de seguridad en un clic.

> [!NOTE]
> **Auditoría de seguridad ofensiva.** Condiciones de carrera, fuerza bruta, redirecciones, inyecciones, archivos trampa: se encontraron 17 vulnerabilidades (2 graves, 7 medias, 8 leves) y todas se corrigieron. Después se repitieron los 13 escenarios de ataque contra la versión corregida: todos neutralizados. Las pruebas automáticas (`php tests/run.php`) cubren gran parte de ellos.

Todos los detalles en la [guía, sección Seguridad](docs/GUIDE.es.md#seguridad).

## Instalación en 3 minutos

<p align="center">
  <img src="docs/images/es/install.webp" alt="Instalación en tres pasos: subir la carpeta, responder cuatro preguntas, el sitio está en línea" width="100%">
</p>

1. **Descarga** el archivo [`eclair-cms-1.1.0.zip`](https://github.com/Jacckisback/eclair-cms/raw/main/dist/eclair-cms-1.1.0.zip) (315 KB) y descomprímelo.
2. **Sube el contenido de la carpeta `eclair`** a la carpeta pública de tu hosting (`public_html`, `www`…), incluidos los archivos ocultos `.htaccess`.
3. **Abre la dirección de tu sitio**: arranca el asistente. Cuatro preguntas, un poco de confeti, y tu sitio está en línea con sus primeras páginas.

**Requisitos**: PHP 8.1 o superior (se recomienda 8.3), Apache o LiteSpeed (casi todos los hostings compartidos: Hostinger, IONOS, OVHcloud…) y un certificado HTTPS. El asistente lo comprueba todo y te explica qué corregir. También funciona con Nginx ([configuración](docs/GUIDE.es.md#nginx)).

> [!TIP]
> Por seguridad, la instalación debe hacerse en las **2 horas** siguientes a la primera visita. Pasado ese plazo, borra `data/install-window.php` por FTP y recarga la página.

## Documentación

La [guía completa](docs/GUIDE.es.md) detalla todo lo demás:

- [Instalación](docs/GUIDE.es.md#instalación): requisitos, tipos de sitio, primeros pasos
- [Funciones en detalle](docs/GUIDE.es.md#funciones-en-detalle): velocidad, SEO, GEO, editor de bloques
- [Uso diario](docs/GUIDE.es.md#uso-diario)
- [Actualizar, hacer copias de seguridad, mudarse](docs/GUIDE.es.md#actualizar-hacer-copias-de-seguridad-mudarse)
- [Solución de problemas](docs/GUIDE.es.md#solución-de-problemas): contraseña olvidada, errores 404 o 500, Nginx…

## Para desarrolladores

PHP 8.1+ sin framework ni Composer: clases sencillas en `eclair/app/lib/`, vistas PHP y JavaScript nativo.

```bash
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t eclair tools/router.php   # servidor de desarrollo
php tests/run.php                                                           # 199 pruebas unitarias y HTTP
php tools/build.php                                                         # archivo listo para distribuir
```

Estructura del proyecto y personalización del tema: consulta [Para desarrolladores](docs/GUIDE.es.md#para-desarrolladores).

## Licencia

Éclair se distribuye con **licencia MIT** ([`LICENSE`](LICENSE)): úsalo, modifícalo y redistribúyelo libremente, también con fines comerciales, siempre que conserves el aviso de copyright. Parte de los iconos del panel deriva de [Lucide](https://lucide.dev) (licencia ISC, consulta [`THIRD-PARTY-NOTICES.txt`](THIRD-PARTY-NOTICES.txt)).

<br>

<p align="center"><sub>⚡ Éclair, para autónomos y pequeñas empresas que quieren una web rápida, bien posicionada y sin complicaciones.</sub></p>

[^wp]: Archivo oficial de WordPress 7.1.4: 37.239.942 bytes, es decir, 35,5 MB ([enlace de descarga](https://downloads.wordpress.org/release/wordpress-7.1.4.zip), comprobado el 29 de octubre de 2026).
