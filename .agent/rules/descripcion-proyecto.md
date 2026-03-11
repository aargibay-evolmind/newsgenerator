---
trigger: always_on
---

# Descripción del proyecto

Este proyecto es una aplicación web que permite crear artículos de noticias en menos de 10 minutos mediante el uso de IA generativa.


## Requisitos

* Que los artículos generados sean lo suficientemente atractivos como para atraer tráfico al blog de noticias.
* Dar control al usuario sobre el contenido y el tono de las noticias generadas.
* Que se pueda generar un artículo con muy pocas directrices, que con indicar un título como "¿Cómo ser Guardia Civil? Pasos para el Ingreso en el Cuerpo" sea suficiente.
  

## Flujo de generación de una noticia

1. **Definición inicial del artículo**
Solicitamos al usuario que rellene varios campos que describen de qué trata el artículo:
  * Título: título descriptivo de lo que va la noticia.
  * Puntos clave: puntos que debe desarrollar la noticia.
  * Urls de referencia: artículos que podemos usar como referencia, un boletín oficial con cambios recientes, etc.

2. **Revisión del plan de generación**
En este paso la IA nos generará un plan que podemos revisar para determinar:
  * El tono que se usará. Por defecto usará un tono amigable pero profesional. Se mostrará un breve extracto como demostración.
  * El tema principal del artículo.
  * Los puntos clave que se desarrollarán.
  * Los enlaces a webs del estado con servicios relevantes a los puntos desarrollados que se incluirán.

Aquí el usuario podrá conversar con la IA para actualizar este plan, añadiendo y/o quitando contenido como vea necesario.

3. **Generación y vista previa del artículo**
Es el último paso. La IA generará un artículo en formato Markdown. Se podrá cambiar entre la vista "editor" y la vista "demo".

En la vista editor el usuario podrá interactuar con un editor WYSIWIG de Markdown donde podrá modificar el artículo. En la vista "demo" el artículo se verá como si se tratase de un artículo publicado, con sus estilos, párrafos, imágenes, etc.