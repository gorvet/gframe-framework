# Medios sintéticos de pruebas

`media-preview.mp3` es un tono de 440 Hz de 0,3 segundos; `media-preview.webm` es un cuadro azul de 32×32 píxeles durante 0,6 segundos. Generados localmente, sin contenido de terceros. Son fixtures del navegador, no recursos publicados por el módulo. Las pruebas no requieren FFmpeg instalado.

Regeneración opcional con FFmpeg:

```sh
ffmpeg -f lavfi -i 'sine=frequency=440:sample_rate=22050:duration=0.3' -c:a libmp3lame -b:a 32k -y tests/fixtures/media-preview.mp3
ffmpeg -f lavfi -i 'color=c=blue:s=32x32:r=5:d=0.6' -c:v libvpx -an -y tests/fixtures/media-preview.webm
ffmpeg -f lavfi -i 'color=c=0x487aab:s=360x240:d=0.1' -frames:v 1 -y tests/fixtures/media-writing-image.png
```

`media-writing-image.png` es un rectángulo azul sintético de 360×240, utilizado para comprobar la composición visual de las galerías. La fixture original de selector sigue usando su imagen mínima salvo que se indique `GFRAME_MEDIA_TEST_IMAGE`.
