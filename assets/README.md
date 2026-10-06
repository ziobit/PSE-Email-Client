# PSE black cat icon

The default application icon and `.pse` Windows icon use the same black cat artwork. PNG files are supplied at 16, 32, 48, 64, 128, 192, 256 and 512 pixels; `pse-black-cat.ico` contains 16, 32, 48, 64, 128 and 256 pixel PNG frames.

All sizes are embedded in `index.php`, so copying only that PHP file to a server still works without GD. The downloaded Windows icon is available from `index.php?pwa=cat-ico`; fixed cat PNGs are available from `index.php?pwa=cat-icon&size=192` or `size=512`. The normal `?pwa=icon` endpoint respects custom application icons. Existing uploaded icons remain intact; only an exact match with the previous generated envelope icon is migrated to the cat.

Artwork was created with the built-in image generation tool, then resized and encoded as indexed PNGs for application use. Production prompt:

> Use case: logo-brand. Asset type: square PWA application and Windows file icon for a personal email client. A polished simple black cat icon on a solid very light warm ivory square background. Large centered front-facing black cat head silhouette, tall triangular ears, two small bright golden-green almond eyes; recognizable at very small sizes. Elegant approachable shape and a slight curious expression. Clean flat vector-like graphic, crisp smooth edges, minimalist modern app icon, no photo texture. Cat fills about 72 percent of the width with generous uniform padding and an overall symmetric composition. Near-black cat, golden-green eyes, warm ivory background. No text, letters, logos, watermark, border, envelope, accessories, shadows or gradients. Full square image without rounded outer corners.
