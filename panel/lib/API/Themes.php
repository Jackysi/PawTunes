<?php

/**
 * PawTunes Project - Open Source Radio Player
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 * This file is part of the PawTunes open-source project.
 * Contributions and feedback are welcome! Visit the repository or website for more details.
 */

namespace API;

class Themes extends Base
{

    public function getThemes()
    {
        $this->sendJSON($this->readThemes());
    }


    private function readThemes()
    {
        // List of templates
        $templates = $this->pawtunes->getTemplates();
        $schemes   = [];

        // Read all custom folders
        foreach ($templates as $template) {
            if (is_dir($template['path'].'/custom')) {

                $list = $this->pawtunes->browse($template['path'].'/custom/');
                foreach ($list as $custom) {
                    $schemes[] = [
                        'name'     => $custom,
                        'template' => $template['name'],
                        'path'     => $template['path'].'/custom/'.$custom,
                        'size'     => $this->pawtunes->formatBytes(filesize($template['path'].'/custom/'.$custom)),
                    ];
                }

            }
        }

        // Sort by template
        usort($schemes, static function ($a, $b) {
            return $a['template'] <=> $b['template'];
        });

        return $schemes;
    }


    public function deleteTheme()
    {
        // POST only (enforced in panel/api.php), so the CSRF gate in panel/index.php has checked the token
        $name = (string) ($_POST['path'] ?? '');
        if ($name) {
            $schemes = $this->readThemes();

            $valid = false;
            foreach ($schemes as $scheme) {
                if ($scheme['path'] === $name) {
                    $valid = true;
                    break;
                }
            }

            if ($valid) {
                $this->sendJSON(['success' => $this->pawtunes::deleteFile($name)]);
            }

            $this->sendJSON(['success' => false]);

        }
    }

}