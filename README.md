# Escape DAM plugin for Craft CMS 3.x

Escape DAM integration

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require /escape-dam

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Escape DAM.

## Escape DAM Overview

-Insert text here-

## Configuring Escape DAM

-Insert text here-

## Using Escape DAM

### Enable videos  

To enable selecting videos from the DAM, the "Video" file kind must be checked under the DAM field's "Restrict allowed file types?" setting.  

### DAM field asset properties and methods

The plugin adds the following extra methods to assets:    

`isDamFile` bool (`true` if the asset was imported from the DAM)  

`isDamImage` bool (`true` if the asset was imported from the DAM, and is an image)  

`isDamVideo` bool (`true`) if the asset was imported from the DAM, and is a video)  

`getMuxPlaybackId()` string|null Returns the Mux playback ID if the asset is an imported video; `null` if not.  

`getDamVideoStreamUrl()` string|null Returns the Mux URL to the HLS stream, if the asset is an imported video; `null` if not.  

`getDamVideoData()` array|null Returns all the DAM data for an imported video asset; `null` if the asset is not a DAM video.  

`getDamVideoTag($params=[], $polyfill=true)`  string|null Returns a video tag if the asset is an imported video asset. Example usage:  

```twig
{% set asset = entry.damField.one() %}
{% if asset and asset.isDamVideo %}
   {{ asset.getDamVideoTag({ autoplay: true, muted: true, poster: false })|attr({ class: 'absolute inset-0 w-full h-full object-fit' }) }} 
{% endif %}
```

`getDamVideoImageUrl($params=[])`  string|null Returns the URL to an image from the video, if the asset is an imported video asset. See https://docs.mux.com/guides/video/get-images-from-a-video#thumbnail-query-string-parameters for possible params.  

`getDamVideoGifUrl()` string|null Returns the URL to an animated GIF from the video, if the asset is an imported video asset. See https://docs.mux.com/guides/video/get-images-from-a-video#animated-gif-query-string-parameters for possible params.

## Escape DAM Roadmap

Some things to do, and ideas for potential features:

* Release it

Brought to you by [Værsågod](https://vaersaagod.no)
