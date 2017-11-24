<?php
/* Icinga Web 2 | (c) 2016 Icinga Development Team | GPLv2+ */

namespace Icinga\Web\Widget;

use HTMLPurifier;
use HTMLPurifier_Config;
use Icinga\Application\Icinga;
use Icinga\Data\Filter\Filter;
use Icinga\Forms\Announcement\AcknowledgeAnnouncementForm;
use Icinga\Web\Announcement\AnnouncementCookie;
use Icinga\Web\Announcement\AnnouncementIniRepository;

/**
 * Render announcements
 */
class Announcements extends AbstractWidget
{
    /**
     * Cache for {@link getPurifier()}
     *
     * @var HTMLPurifier
     */
    protected $purifier;

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        $repo = new AnnouncementIniRepository();
        $etag = $repo->getEtag();
        $cookie = new AnnouncementCookie();
        if ($cookie->getEtag() !== $etag) {
            $cookie->setEtag($etag);
            $cookie->setNextActive($repo->findNextActive());
            Icinga::app()->getResponse()->setCookie($cookie);
        }
        $acked = array();
        foreach ($cookie->getAcknowledged() as $hash) {
            $acked[] = Filter::expression('hash', '!=', $hash);
        }
        $acked = Filter::matchAll($acked);
        $announcements = $repo->findActive();
        $announcements->applyFilter($acked);
        if ($announcements->hasResult()) {
            $html = '<ul role="alert" id="announcements">';
            foreach ($announcements as $announcement) {
                $ackForm = new AcknowledgeAnnouncementForm();
                $ackForm->populate(array('hash' => $announcement->hash));
                $html .= '<li><div>'
                    . $this->getPurifier()->purify($announcement->message)
                    . '</div>'
                    . $ackForm
                    . '</li>';
            }
            $html .= '</ul>';
            return $html;
        }
        // Force container update on XHR
        return '<div style="display: none;"></div>';
    }

    /**
     * Initialize and return HTML purifier for announcements' messages
     *
     * @return HTMLPurifier
     */
    protected function getPurifier()
    {
        if ($this->purifier === null) {
            require_once 'HTMLPurifier/Bootstrap.php';
            require_once 'HTMLPurifier.php';
            require_once 'HTMLPurifier.autoload.php';

            $config = HTMLPurifier_Config::createDefault();
            $config->set('Core.EscapeNonASCIICharacters', true);
            $config->set('HTML.Allowed', 'b,a[href|target],i,*[class]');
            $config->set('Attr.AllowedFrameTargets', array('_blank'));
            // This avoids permission problems:
            // $config->set('Core.DefinitionCache', null);
            $config->set('Cache.DefinitionImpl', null);
            // TODO: Use a cache directory:
            // $config->set('Cache.SerializerPath', '/var/spool/whatever');

            // $config->set('URI.Base', 'http://www.example.com');
            // $config->set('URI.MakeAbsolute', true);
            // $config->set('AutoFormat.AutoParagraph', true);
            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier;
    }
}
