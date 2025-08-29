![PawTunes Logo](https://cdn.prahec.com/projects/pawtunes/pawtunes-dark.png)
# PawTunes Internet Radio Player (Web App) 🎵

PawTunes is a powerful and versatile web-based internet radio player designed for both radio stations and music enthusiasts.
Built from the ground up with over 15 years of experience, PawTunes combines cutting-edge technology, elegant design, 
and unparalleled performance to deliver the ultimate radio player experience.
It integrates templates and knowledge from all my previously created radio players: "Radio Player," "Pro Radio Player," and "AIO Radio Station Player."

This is not just a simple JavaScript library for playing music, it's a complete platform/app that fetches Track Information and Artworks for your live radio stream. 
Its versatility allows seamless integration with many APIs, and more can easily be added using the "TrackInfo" class.
All requests are cached for the refresh time configured, minimizing unnecessary API calls.
Additionally, there's a small Go app I developed that connects to the stream and provides "Live Info" from a lightweight service, 
including history and HTTPS proxying (if you provide a certificate).
But more on that later.

The great thing is that these images are cropped, optimized, and stored on **PawTunes** for fast access. 
This feature can be disabled in the settings, using only direct API requests, which are also cached.

---

## 🌐 Demo & Screen shots

Explore the live demo:
- **Control Panel**: [https://prahec.com/projects/pawtunes/demo/panel/](https://prahec.com/projects/pawtunes/demo/panel/)  
  **Username**: `admin`  
  **Password**: `password`

[![PawTunes Template](https://cdn.prahec.com/projects/pawtunes/screens/20241129175123.png)](https://cdn.prahec.com/projects/pawtunes/screens/20241129175123.png)

[![PawTunes Template](https://cdn.prahec.com/projects/pawtunes/screens/20241129175330.png)](https://cdn.prahec.com/projects/pawtunes/screens/20241129175330.png)

[![PawTunes Template](https://cdn.prahec.com/projects/pawtunes/screens/20241129175356.png)](https://cdn.prahec.com/projects/pawtunes/screens/20241129175356.png)

[![PawTunes Template](https://cdn.prahec.com/projects/pawtunes/screens/20241129175419.png)](https://cdn.prahec.com/projects/pawtunes/screens/20241129175419.png)

---

## 🚀 Features

### 🖥️ Frontend
- **Responsive Design**: Optimized for seamless performance on desktops, tablets, and mobiles.
- **HTML5 Audio API**: Fully compatible with all major browsers, ensuring a consistent experience.
- **Unlimited Multi-Channels**: Configure an unlimited number of channels per player.
- **Multi-bitrate Streaming**: Users can select their preferred stream quality (optional).
- **Multi-language Support**: Automatically adjusts based on browser locale.
- **Dynamic Playlist Generation**: Create streaming playlists on the fly with PHP.
- **Built-in WebSocket Support**: Ensures low-latency communication.
- **Artwork Caching and Management**: Fetch and store images from APIs like Spotify, iTunes, and FanArtTV.
- **Customizable Themes**: Includes multiple templates with the ability to create custom colour schemes.
- **Dynamic Window Title**: Updates the browser window title dynamically based on the current track info.
- **MediaInfoAPI**: Similar to YouTube, **PawTunes** displays artwork/track info on any Bluetooth device.
- **Easy Customization**: With minimal development skills, you can tailor the front-end to your preferences.
- **Stream History**: Track stream history for each channel using API data or generate it dynamically.
- **Auto-Reconnect**: Automatically reconnects streams in case of browser disconnection or network errors.
- **and much more...**

### 🔧 Backend
- **PHP Backend**: Powers advanced features like track info, artwork management, API integrations, and more
- **Caching Options**: Supports APCu, Redis, Memcached, and disk-based caching for high performance.
- **Control Panel**: Manage all settings, templates, and tracks via an intuitive dashboard.
- **API Support**: Integrates seamlessly with APIs like Spotify and FanArtTV for enhanced metadata (details below).
- **Templates**: Use variables in HTML templates, with future support planned for the Blade templating engine.
- **Advanced Template Options**: Add custom templates with metadata-driven options (e.g. enable/disable spectrum, song search URL and much more) see `metadata.json` in the template folders.
- **and more...**

### 🌍 Track Info APIs
- **Any stream** with embedded ICY-METADATA (used by most streams today).
- **Shoutcast** (Public & Admin access).
- **Icecast** (Admin access required).
- **AzuraCast** (Web Sockets and API integration).
- **Sam Broadcaster** (via database integration).
- **CentovaCast** (Public widget API).
- **Custom** (Use external APIs of your choice).

### 🌍 Artwork APIs
- **iTunes**: Public API, no API key required.
- **Spotify**: Public API, API key required.
- **LastFM**: Public API, API key required.
- **FanArt TV**: Public API, API key required.
- **Custom**: Use your own sources, e.g., point to a folder or a URL like `https://page.com/{{$artist}}%20-%20{{$title}}.jpg`. You can also integrate with any other artwork service.

---

## 🛠 Installation
Installation is straightforward in most cases, download the shared "ZIP" file and upload its contents to your web host. 
Since **PawTunes** is a standalone PHP script/app, you only need to upload the files to a folder of your choice and then access that folder using the following URL format:  
`http://your-host.com/folder-name/panel/index.php`

> [!NOTE]
> Default Login Information  
> Username: **admin**  
> Password: **password**

If you encounter any issues during installation, please refer to the [Installation Guide](https://doc.prahec.com/pawtunes#installation) for detailed instructions.

I also offer installation and customization services. For more details, feel free to reach out via the [Prahec - Contact Me](https://prahec.com/contact) page.

### 📦 Docker
For system insulation and ease of start, I would suggest Docker image. It's super easy to start with a single command:
```
docker run -d -p 80:80 jackyprahec/pawtunes:latest
```

To persist data and configuration files through different docker images, you can mount/copy these folders:
```
/var/www/html/inc/config
/var/www/html/inc/locale
/var/www/html/data
```

---

## 🤝Developer-Friendly
**PawTunes** is designed to be easily extended, upgraded, and customized.
The codebase is straightforward, well-commented, and structured for simplicity.
Full documentation for all player classes and functions is coming soon.
In the meantime, you can find practical code examples for integrating the player into your website using the **External API (JSONP)** here:
[Developer Documentation](https://doc.prahec.com/pawtunes#developers).

#### Experimental feature: ICY Metadata
For years, reading ICY metadata directly in the browser seemed impossible without Web Assembly. 
Now, we're pushing the boundaries with an experimental implementation. 
It's still a work in progress, currently in beta, and may not function on some mobile browsers or older environments.
Check out the demo and feel free to contribute or provide feedback: [Live ICY Metadata Demo](https://prahec.com/pawdemo/)

---

### 📈 Scalability
- **High Performance**: Optimized to handle thousands of simultaneous listeners.
- **Caching** using APC, APCu, Redis, Memcached, and Disk cache (can be on shared storage)

---

## 📜 Documentation
Comprehensive documentation is available at:
- [PawTunes Official Docs](https://doc.prahec.com/pawtunes)

---

## ⚙️ Requirements
PawTunes has minimal requirements to ensure smooth operation:
- **PHP 7.4+** with CURL Extension.
- **PHP ZipArchive Extension** (optional, for updates).
- **Access to API Ports**: Ensure proper configuration for ports (e.g., Shoutcast uses port 8000 by default).

---

## ☕ Donations
This project took an immense amount of effort to build. While it may not look like much, 
it represents over 5 months of initial (first release) full-time work and over a decade of experience developing similar apps and scripts. 
My initial plan was to sell this as a product on Envato Market — CodeCanyon for 35$ (about 31€), but the review team permanently declined the project,
so I decided to share this awesome project for free.

As this project is close to my heart, I will continue working on it for free. However, 
if you find it useful and want to support its ongoing development, any financial support would be greatly appreciated. 
It would help cover costs (hosting) my effort and, of course, fund a coffee or two to keep me coding! ☕

💸[Donate via PayPal](https://www.paypal.com/donate/?hosted_button_id=VN3SBVYNHC2SE)

I will also be extremely grateful for any help solving issues and/or improving my code for others.

---

## ⭐ Credits
**PawTunes** would have taken even more time to develop without the incredible open-source projects that made it possible. 
Here’s the list of libraries and projects used in the **Control Panel**:

- **FontAwesome 6 Free**: [https://fontawesome.com/](https://fontawesome.com/)
- **Bootstrap Modals**: [https://getbootstrap.com/docs/5.3/components/modal/](https://getbootstrap.com/docs/5.3/components/modal/)
- **SCSS PHP Compiler**: For custom colour schemes [https://scssphp.github.io/scssphp/](https://scssphp.github.io/scssphp/)
- **Spectrum**: For colour picking of templates [https://bgrins.github.io/spectrum/](https://bgrins.github.io/spectrum/)
- **jQuery**: [https://jquery.com/](https://jquery.com/)

The player itself uses only one lightweight library:

- **AudioMotion Analyser**: [https://www.npmjs.com/package/audiomotion-analyzer](https://www.npmjs.com/package/audiomotion-analyzer)

If I’ve missed crediting any library, please let me know, and I will update this list promptly.