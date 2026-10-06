# WordPress.org directory icons

The existing FontReady purple F/check mark is available as:

- icon-128x128.gif and icon-256x256.gif: animated check reveal.
- icon-128x128.png and icon-256x256.png: still-image alternatives.
- icon.svg: scalable still-image alternative.

After approval, copy the chosen GIF pair to the assigned SVN repository's top-level `assets` directory, alongside `trunk` and `tags`. These directory images are deliberately excluded from the installable plugin ZIP. A ZIP submission alone does not set the listing icon.

For an animated listing, use the GIF pair. Keep the PNG and SVG alternatives in this development repository; uploading the SVG as well may cause a directory view to prefer the still vector. Animation playback across directory and WordPress admin views has not been verified. If a view processes the GIF as a still image, the first frame is the complete recognizable logo.

Regenerate with `pip install -r requirements-assets.txt` then `python scripts/build_icons.py`. Icons are below the documented 1 MB limit. Set `svn:mime-type` to `image/gif` when uploading GIFs.

Reference: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
