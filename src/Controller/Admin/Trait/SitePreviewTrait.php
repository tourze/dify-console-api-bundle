<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Controller\Admin\Trait;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Tourze\DifyConsoleApiBundle\Entity\BaseApp;

trait SitePreviewTrait
{
    /**
     * 创建站点预览Action
     */
    protected function createSitePreviewAction(string $appType = ''): Action
    {
        return Action::new('sitePreview', '预览站点', 'fas fa-external-link-alt')
            ->linkToUrl(
                function (BaseApp $entity) use ($appType): string {
                    $site = $entity->getSite();
                    if (null === $site || !$site->isEnabled()) {
                        return '#';
                    }

                    $siteUrl = $site->getSiteUrl();
                    if ('' === $siteUrl) {
                        return '#';
                    }

                    $appName = $entity->getName();
                    // 转义单引号避免JavaScript错误
                    $appName = str_replace("'", "\\'", $appName);

                    return "javascript:openSitePreview('{$siteUrl}', '{$appName}', '{$appType}');";
                }
            )
            ->displayIf(
                function (BaseApp $entity): bool {
                    $site = $entity->getSite();
                    if (null === $site || !$site->isEnabled()) {
                        return false;
                    }

                    $siteUrl = trim($site->getSiteUrl());

                    return '' !== $siteUrl;
                }
            )
        ;
    }

    /**
     * 配置站点预览资源
     */
    protected function configureSitePreviewAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('/bundles/difyconsoleapi/js/site-preview.js');
    }
}
