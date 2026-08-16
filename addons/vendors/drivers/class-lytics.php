<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Vendor_Lytics extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'lytics';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['tag_id'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Lytics', 'default' => true, 'description' => 'Customer data platform used for audience segmentation/personalization behind the scenes — a reader never sees anything from this directly.' ),
			array( 'key' => 'tag_id', 'type' => 'text', 'label' => 'Tag ID', 'default' => '83288ca484b4febdd7907bd820c502cd', 'description' => 'The Lytics JS tag ID from your Lytics account settings.' ),
		);
	}

	public function print_head(): void {
		$tag_id = $this->option()['tag_id'];
		?>
		<script type="text/javascript">
		!function(){"use strict";var o=window.jstag||(window.jstag={}),r=[];function n(e){o[e]=function(){for(var n=arguments.length,t=new Array(n),i=0;i<n;i++)t[i]=arguments[i];r.push([e,t])}}n("send"),n("mock"),n("identify"),n("pageView"),n("unblock"),n("getid"),n("setid"),n("loadEntity"),n("getEntity"),n("on"),n("once"),n("call"),o.loadScript=function(n,t,i){var e=document.createElement("script");e.async=!0,e.src=n,e.onload=t,e.onerror=i;var o=document.getElementsByTagName("script")[0],r=o&&o.parentNode||document.head||document.body,c=o||r.lastChild;return null!=c?r.insertBefore(e,c):r.appendChild(e),this},o.init=function n(t){return this.config=t,this.loadScript(t.src,function(){if(o.init===n)throw new Error("Load error!");o.init(o.config),function(){for(var n=0;n<r.length;n++){var t=r[n][0],i=r[n][1];o[t].apply(o,i)}r=void 0}()}),this}}();
		jstag.init({ src: 'https://c.lytics.io/api/tag/<?php echo esc_js( $tag_id ); ?>/latest.min.js', pageAnalysis: { dataLayerPull: { disabled: true } } });
		jstag.pageView();
		</script>
		<?php
	}
}
