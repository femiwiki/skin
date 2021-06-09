<?php

namespace MediaWiki\Skins\Femiwiki;

use OOUI\ButtonWidget;
use OutputPage;
use Sanitizer;
use SkinMustache;
use SpecialPage;

/**
 * Skin subclass for Femiwiki
 *
 * @ingroup Skins
 * @internal
 */
class SkinFemiwiki extends SkinMustache {

	/** @var array|mixed */
	private $xeIconMap;

	/**
	 * @inheritDoc
	 */
	public function __construct( $options = [] ) {
		if ( $this->getUser()->isLoggedIn() ) {
			$options['scripts'][] = 'skins.femiwiki.notifications';
		}
		if ( !$this->getTitle()->isSpecialPage() ) {
			$options['scripts'][] = 'skins.femiwiki.share';
		}
		parent::__construct( $options );
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData() : array {
		$skin = $this;
		$out = $skin->getOutput();
		$title = $out->getTitle();
		$config = $this->getConfig();
		$parentData = parent::getTemplateData();
		$sidebar = $this->getSidebar( $toolbox );

		$commonSkinData = array_merge_recursive( $parentData, [
			'data-sidebar' => $sidebar,
			'html-heading-language-attributes' => $this->prepareHeadingLanguageAttributes,
			'html-share-button' => $this->getShare(),
			'msg-page-menu-toggle-tooltip' => $this->msg( 'skin-femiwiki-page-menu-tooltip' )->text(),
			'data-toolbox' => $toolbox,
			'html-lastmod' => $this->lastModified(),
			'text-add-this-pub-id' => $this->getAddThisPubId(),
			'has-footer-icons' => $config->get( Constants::CONFIG_KEY_SHOW_FOOTER_ICONS ),
			'has-indicator' => count( $parentData['array-indicators'] ) !== 0,

			// links
			'link-recentchanges' => SpecialPage::getTitleFor( 'Recentchanges' )->getLocalUrl(),
			'link-random' => SpecialPage::getTitleFor( 'Randompage' )->getLocalUrl(),
			'link-history' => $title->getLocalURL( 'action=history' ),
		] );

		return $commonSkinData;
	}

	/**
	 * Returns data for the sidebar.
	 * 'data-portlets-sidebar' also provides it, but it is divided into two parts.
	 * The division is useful for Vector, but not useful for other skins.
	 * @param array &$toolbox
	 * @return array
	 */
	protected function getSidebar( &$toolbox ) {
		$sidebarData = $this->buildSidebar();
		$sidebar = [];
		foreach ( $sidebarData as $name => $items ) {
			if ( $name == 'TOOLBOX' ) {
				// The toolbox includes both page-specific-tools and site-wide-tools, but we
				// need only page-specific-tools, so unset those.
				foreach ( [ 'specialpages', 'upload' ] as $item ) {
					unset( $items[$item] );
				}
				$toolbox = $this->getPortletData( $name, $items );
				continue;
			} elseif ( in_array( $name, [ 'SEARCH', 'LANGUAGES' ] ) ) {
				continue;
			}
			$sidebar[] = $this->getPortletData( $name, $items );
		}
		return $sidebar;
	}

	/**
	 * @return string|null
	 */
	protected function getShare() {
		if ( $this->getOutput()->getTitle()->getArticleID() === 0 ) {
			return null;
		}
		return new ButtonWidget( [
			'id' => 'p-share',
			'classes' => [ 'fw-button' ],
			'infusable' => true,
			'icon' => 'share',
			'title' => $this->msg( 'skin-femiwiki-share-tooltip' )->escaped(),
			'framed' => false,
			'invisibleLabel' => true
		] );
	}

	/**
	 * @return string HTML attributes
	 */
	protected function prepareHeadingLanguageAttributes() {
		$config = $this->getConfig();
		if ( !$config->get( Constant::CONFIG_KEY_USE_PAGE_LANG_FOR_HEADING ) ) {
			return parent::prepareUserLanguageAttributes();
		}
		// Use page language for the first heading.
		$title = $this->getOutput()->getTitle();
		$pageLang = $title->getPageViewLanguage();
		$pageLangCode->getHtmlCode();
		$pageLangDir = $pageLang->getDir();
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		if (
			$pageLangCode !== $contLang->getHtmlCode() ||
			$pageLangDir !== $contLang->getDir()
		) {
			$escPageLang = htmlspecialchars( $pageLangCode );
			$escPageDir = htmlspecialchars( $pageLangDir );
			// Attributes must be in double quotes because htmlspecialchars() doesn't
			// escape single quotes
			return " lang=\"$escPageLang\" dir=\"$escPageDir\"";
		}
		return '';
	}

	/**
	 * @return array|mixed
	 */
	private function getXeIconMap() {
		if ( !$this->xeIconMap ) {
			$map = json_decode(
				$this->msg( 'skin-femiwiki-xeicon-map.json' )
					->inContentLanguage()
					->plain(),
				true
			);
			foreach ( $map as $k => $v ) {
				$escapedId = Sanitizer::escapeIdForAttribute( $k );
				if ( $k != $escapedId ) {
					$map[$escapedId] = $v;
					unset( $map[$k] );
				}
			}

			$this->xeIconMap = $map;
		}
		return $this->xeIconMap;
	}

	/**
	 * Extends to prepend xe-icons
	 * @inheritDoc
	 */
	protected function getPortletData( $name, array $items ) {
		$xeIconMap = $this->getXeIconMap();

		$htmlItems = '';
		foreach ( $items as $key => $item ) {
			$options = [
				'text-wrapper' => [
					[
						'tag' => 'i',
						'attributes' => [
							'class' => 'xi-' . $xeIconMap[$item['id']]
						]
					],
					[
						'tag' => 'span'
					],
				],
				'link-class' => 'xe-icons',
			];
			$htmlItems .= $this->makeListItem( $key, $item, $options );
		}

		$parentData = parent::getPortletData( $name, $items );
		$parentData['html-items'] = $htmlItems;
		return $parentData;
	}

	/**
	 * Extends Skin::initPage.
	 *
	 * @inheritDoc
	 */
	public function initPage( OutputPage $out ) {
		$out->addMeta( 'viewport', 'width=device-width, initial-scale=1.0' );

		$twitter = $this->getConfig()->get( 'FemiwikiTwitterAccount' );
		if ( $twitter ) {
			$out->addMeta( 'twitter:site', "@$twitter" );
		}

		// Favicons
		$headItems = $this->getConfig()->get( 'FemiwikiHeadItems' );
		if ( $headItems ) {
			$out->addHeadItems( $headItems );
		}

		# Always enable OOUI because OOUI icons are used in FemiwikiTemplate class
		$out->enableOOUI();
		parent::initPage( $out );
	}

	/** @return string|null */
	private function getAddThisPubId() {
		$config = $this->getConfig()->get( 'FemiwikiAddThisId' );
		if ( !$config ) {
			return null;
		}
		if ( is_array( $config ) ) {
			return $config['pub'] ?? null;
		}
		return $config;
	}
}
