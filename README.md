# Purge Cloudflare Cache From Wordpress

- This plugin is for those who would like to speed-up their Wordpress Sites activating the CACHE EVERYTHING page rule in Cloudflare.

- With this plugin you can purge the content in Cloudflare directly from Wordpress each time you edit your pages, posts, categories, tags, taxonomies, custom post types and images.

- Each time you edit a category, tag, or custom taxonomies all the related posts are also flushed in Cloudflare.

- Each time you edit the navigation menu or a widget ALL your pages are flushed in Cloudflare.

- Vistors will never see the admin bar.

## Easy Configuration

![menu](/assets/menu.png)

![settings](/assets/settings.png)

## Usage

After initial setup there are no addininal actions you'll need to do.

## Developers

You can purge EVERYTHING, a URL, or an ARRAY of URLs using the following codes:

Purge everything:

```
Purge_Cloudflare::purge();
```

Purge a single URL:

```
Purge_Cloudflare::purge("https://jaimelias.com");
```

Purge an ARRAY of URLs:

```
Purge_Cloudflare::purge(array("https://jaimelias.com", "https://jaimelias.com/"));
```

Note: URLs are purged in sets of 30.

## Debug

- If debugging is activated errors and the list of flushed pages will appear in the file: /wp-content/plugins/purge-cloudflare/debug.log