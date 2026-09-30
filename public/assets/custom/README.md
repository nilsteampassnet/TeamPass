# Custom branding images

Put the images used by the login page here, then enter their file name in
**Settings → Options**:

- **Custom login logo**: for example `logo.png`
- **Custom login background**: for example `background.jpg`

Enter the file name alone, without any folder: TeamPass looks for it in this folder.
Accepted formats: PNG, JPG, GIF and WebP.

TeamPass only ships this README and the `.htaccess` file here: an upgrade never replaces
your images.
With Docker, mount your images into `/var/www/html/public/assets/custom/`
(see the Docker installation guide).
