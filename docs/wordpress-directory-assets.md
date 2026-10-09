# WordPress directory assets

The `.wordpress-org/` folder is the prepared directory asset kit. These images belong in the **top-level SVN `assets` directory**, beside `trunk` and `tags`, after WordPress.org approval grants SVN access. They are not published to the directory merely by adding them to GitHub or the install ZIP.

Prepared names follow the [official asset specification](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/):

- `icon.svg`, `icon-128x128.png`, `icon-256x256.png`
- `banner-772x250.png`, `banner-1544x500.png`
- Numbered screenshot PNGs with matching numbered captions in `readme.txt`

Editable artwork and usage guidance live in `design/brand/`. Directory screenshots must remain actual plugin captures, use generic content and contain no credentials or client data. Re-capture screenshots whenever the depicted interface materially changes; rebuilding vectors does not regenerate screenshots.

For the approved SVN asset checkout, copy only these directory files into its `assets` folder, review `svn status` and `svn diff`, and apply appropriate image MIME properties before an authorized commit:

```sh
svn propset svn:mime-type image/png assets/*.png
svn propset svn:mime-type image/svg+xml assets/icon.svg
```

Submission and directory approval remain pending. Do not publish a directory status, downloads claim or compatibility promise that has not been verified.
