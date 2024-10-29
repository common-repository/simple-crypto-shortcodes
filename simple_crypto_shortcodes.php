<?php
/*
Plugin Name: Simple Crypto Shortcodes
Plugin URI: https://bitcoinminingsoftware.com/simple-crypto-shortcodes-wordpress-plugin/
Description: Simple shortcodes to show crypto prices and other data from CoinMarketCap API!
Version: 1.0.2
Author: stefanristic
Author URI: https://migratewptoday.com
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Text Domain: simple_crypto_shortcodes
*/

add_action( 'admin_menu', 'scs_register_my_custom_menu_page' );

function scs_register_my_custom_menu_page() {
	add_menu_page( 'Simple Crypto Shortcodes', 'Simple Crypto Shortcodes', 'manage_options', 'simple_crypto_shortcodes', 'scs_backend', 'dashicons-chart-area', 90 );
	if(get_option('scs_cache_time') == false) {
		update_option('scs_cache_time', 600);
	}
}

add_action('admin_head', 'scs_custom_css');

function scs_custom_css() {
  echo '<style>
    #api_key, #cache_time {
		width: 100%;
		max-width: 320px;
		margin-bottom: 15px;
	}
  </style>';
}

function scs_backend(){
    echo '<h1>Simple Crypto Shortcodes</h1>';
	echo '<p>In order for this plugin to pull the latest crypto prices, you need a free API key from CoinMarketCap, which you can make <a href="https://pro.coinmarketcap.com/signup/" target="_blank">here</a>.</p>';
	echo '<p>Once you get the free API key from CoinMarketCap, simply enter it below and hit save.</p>';
	echo '<p>The limit of the free API key is ~333 requests per day, if you have a website with lots of traffic you may need more requests, in which case you should sign up for a higher tier package.<br>But you can certainly try with the free API key and see how it works.</p>';
	echo '<h2>Enter your CoinMarketCap API key below:</h2>';
	if(!$_POST['api_key'] && get_option('cmc_api_key') == false) {
	echo '<form action="" method="post"><input id="api_key" name="api_key" type="text" placeholder="API key"><br><input type="submit" class="button button-primary" value="Save your API key"></form>';
	} elseif(get_option('cmc_api_key') != false && !$_POST['api_key']) {
		echo '<form action="" method="post"><input id="api_key" name="api_key" type="text" value="' . esc_attr(get_option('cmc_api_key')) . '"><input type="submit" class="button button-primary" value="Save your API key"></form>';
	} elseif($_POST['api_key']) {
		update_option('cmc_api_key', sanitize_text_field($_POST['api_key']));
		echo '<form action="" method="post"><input id="api_key" name="api_key" type="text" value="' . esc_attr(get_option('cmc_api_key')) . '"><input type="submit" class="button button-primary" value="Save your API key"></form>';
	}
	echo '<h2>Crypto Data Caching</h2>';
	echo '<p>Enter cache time for crypto data in seconds(without "s"). This is very useful if you have a large site, or need data pulled for lots of coins, and especially if you have a free API key from CoinMarketCap.<br>The higher the number, the less often will this plugin interact with the CoinMarketCap API, allowing you to preserve your API calls.<br>But, the higher the number, the less updated prices and other data though, so pick a number that works for you in both ways.</p>';
	if(!$_POST['cache_time'] && get_option('scs_cache_time') == false) {
	echo '<form action="" method="post"><input id="cache_time" name="cache_time" type="number" placeholder="Cache life in seconds(defaults to 600s if empty)" required><input type="submit" class="button button-primary" value="Save caching time"></form>';
	} elseif(get_option('scs_cache_time') != false && !$_POST['cache_time']) {
		echo '<form action="" method="post"><input id="cache_time" name="cache_time" type="number" value="' . esc_attr(get_option('scs_cache_time')) . '"><input type="submit" class="button button-primary" value="Save caching time"></form>';
	} elseif($_POST['cache_time']) {
		update_option('scs_cache_time', sanitize_text_field($_POST['cache_time']));
		echo '<form action="" method="post"><input id="cache_time" name="cache_time" type="number" value="' . esc_attr(get_option('scs_cache_time')) . '"><input type="submit" class="button button-primary" value="Save caching time"></form>';
	}
}

function scs_get_coin_info($coin_symbol = 'BTC') {
	$data_option_name = $coin_symbol . '_market_data';
	$timestamp_option_name = $coin_symbol . '_market_timestamp';
	$current_timestamp = date('Y-m-d\TH:i:s'.substr((string)microtime(), 1, 4).'\Z');
	$cache_time = get_option('scs_cache_time');
	if($cache_time == false) {
		$cache_time = 600;
	}
	if(get_option($timestamp_option_name) && (strtotime($current_timestamp) - strtotime(get_option($timestamp_option_name))) < $cache_time) {
		return get_option($data_option_name);
	} else {
	$url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';
	$parameters = [
	  'symbol' => $coin_symbol,
	];

	$qs = http_build_query($parameters); // query string encode the parameters
	$request = "{$url}?{$qs}"; // create the request URL
	$args = array(
		'headers' => array(
			'Accepts' => 'application/json',
			'X-CMC_PRO_API_KEY' => get_option('cmc_api_key'),
		)
	);
	$response = wp_remote_retrieve_body(wp_remote_get( $request, $args ));
	update_option($data_option_name, $response);
	update_option($timestamp_option_name, $current_timestamp);
	return $response;
	}
}
add_shortcode( 'coin_price', 'scs_get_coin_price' );
function scs_get_coin_price( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_price symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		if($data->data->$symbol->quote->USD->price >= 1) {
			return number_format($data->data->$symbol->quote->USD->price, 2) . ' USD';
		} elseif($data->data->$symbol->quote->USD->price * 100 < 1 && $data->data->$symbol->quote->USD->price * 1000 > 1) {
			return number_format($data->data->$symbol->quote->USD->price, 4) . ' USD';
		} elseif($data->data->$symbol->quote->USD->price * 1000 < 1 && $data->data->$symbol->quote->USD->price * 10000 > 1) {
			return number_format($data->data->$symbol->quote->USD->price, 5) . ' USD';
		} elseif($data->data->$symbol->quote->USD->price * 10000 < 1 && $data->data->$symbol->quote->USD->price * 100000 > 1) {
			return number_format($data->data->$symbol->quote->USD->price, 6) . ' USD';
		} elseif($data->data->$symbol->quote->USD->price * 100000 < 1 && $data->data->$symbol->quote->USD->price * 1000000 > 1) {
			return number_format($data->data->$symbol->quote->USD->price, 7) . ' USD';
		} elseif($data->data->$symbol->quote->USD->price * 1000000 < 1 && $data->data->$symbol->quote->USD->price * 10000000 > 1) {
			return number_format($data->data->$symbol->quote->USD->price, 8) . ' USD';
	}
}
}

add_shortcode( 'coin_name', 'scs_get_coin_name' );
function scs_get_coin_name( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_name symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		return $data->data->$symbol->name;
	}
}

add_shortcode( 'coin_max_supply', 'scs_get_coin_max_supply' );
function scs_get_coin_max_supply( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_max_supply symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		return $data->data->$symbol->max_supply;
	}
}

add_shortcode( 'coin_circulating_supply', 'scs_get_coin_circulating_supply' );
function scs_get_coin_circulating_supply( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_circulating_supply symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		return $data->data->$symbol->circulating_supply;
	}
}

add_shortcode( 'coin_rank', 'scs_get_coin_rank' );
function scs_get_coin_rank( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_rank symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		return $data->data->$symbol->cmc_rank;
	}
}

add_shortcode( 'coin_volume_24h', 'scs_get_coin_volume_24h' );
function scs_get_coin_volume_24h( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_volume_24h symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		return $data->data->$symbol->quote->USD->volume_24h . ' USD';
	}
}

add_shortcode( 'coin_percent_change_1h', 'scs_get_coin_percent_change_1h' );
function scs_get_coin_percent_change_1h( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_percent_change_1h symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		if($data->data->$symbol->quote->USD->percent_change_1h > 0) {
			$span_before = '<span style="color:green;">';
			$span_after = '</span>';
		} elseif($data->data->$symbol->quote->USD->percent_change_1h < 0) {
			$span_before = '<span style="color:red;">';
			$span_after = '</span>';
		} else {
			$span_before = '';
			$span_after = '';
		}
		return $span_before . round($data->data->$symbol->quote->USD->percent_change_1h, 3) . '%'  . $span_after;
	}
}

add_shortcode( 'coin_percent_change_24h', 'scs_get_coin_percent_change_24h' );
function scs_get_coin_percent_change_24h( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_percent_change_24h symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		if($data->data->$symbol->quote->USD->percent_change_24h > 0) {
			$span_before = '<span style="color:green;">';
			$span_after = '</span>';
		} elseif($data->data->$symbol->quote->USD->percent_change_24h < 0) {
			$span_before = '<span style="color:red;">';
			$span_after = '</span>';
		} else {
			$span_before = '';
			$span_after = '';
		}
		return $span_before . round($data->data->$symbol->quote->USD->percent_change_24h, 3) . '%'  . $span_after;
	}
}

add_shortcode( 'coin_percent_change_7d', 'scs_get_coin_percent_change_7d' );
function scs_get_coin_percent_change_7d( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_percent_change_7d symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		if($data->data->$symbol->quote->USD->percent_change_7d > 0) {
			$span_before = '<span style="color:green;">';
			$span_after = '</span>';
		} elseif($data->data->$symbol->quote->USD->percent_change_7d < 0) {
			$span_before = '<span style="color:red;">';
			$span_after = '</span>';
		} else {
			$span_before = '';
			$span_after = '';
		}
		return $span_before . round($data->data->$symbol->quote->USD->percent_change_7d, 3) . '%'  . $span_after;
	}
}

add_shortcode( 'coin_percent_change_30d', 'scs_get_coin_percent_change_30d' );
function scs_get_coin_percent_change_30d( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_percent_change_30d symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		if($data->data->$symbol->quote->USD->percent_change_30d > 0) {
			$span_before = '<span style="color:green;">';
			$span_after = '</span>';
		} elseif($data->data->$symbol->quote->USD->percent_change_30d < 0) {
			$span_before = '<span style="color:red;">';
			$span_after = '</span>';
		} else {
			$span_before = '';
			$span_after = '';
		}
		return $span_before . round($data->data->$symbol->quote->USD->percent_change_30d, 3) . '%'  . $span_after;
	}
}

add_shortcode( 'coin_percent_change_60d', 'scs_get_coin_percent_change_60d' );
function scs_get_coin_percent_change_60d( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_percent_change_60d symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		if($data->data->$symbol->quote->USD->percent_change_60d > 0) {
			$span_before = '<span style="color:green;">';
			$span_after = '</span>';
		} elseif($data->data->$symbol->quote->USD->percent_change_60d < 0) {
			$span_before = '<span style="color:red;">';
			$span_after = '</span>';
		} else {
			$span_before = '';
			$span_after = '';
		}
		return $span_before . round($data->data->$symbol->quote->USD->percent_change_60d, 3) . '%'  . $span_after;
	}
}

add_shortcode( 'coin_percent_change_90d', 'scs_get_coin_percent_change_90d' );
function scs_get_coin_percent_change_90d( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_percent_change_90d symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		if($data->data->$symbol->quote->USD->percent_change_90d > 0) {
			$span_before = '<span style="color:green;">';
			$span_after = '</span>';
		} elseif($data->data->$symbol->quote->USD->percent_change_90d < 0) {
			$span_before = '<span style="color:red;">';
			$span_after = '</span>';
		} else {
			$span_before = '';
			$span_after = '';
		}
		return $span_before . round($data->data->$symbol->quote->USD->percent_change_90d, 3) . '%'  . $span_after;
	}
}

add_shortcode( 'coin_cap', 'scs_get_coin_market_cap' );
function scs_get_coin_market_cap( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_cap symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		return $data->data->$symbol->quote->USD->market_cap . ' USD';
	}
}

add_shortcode( 'coin_market_cap_dominance', 'scs_get_coin_market_cap_dominance' );
function scs_get_coin_market_cap_dominance( $atts ) {
	extract(shortcode_atts(array(
		'symbol' => 'xxx',
	), $atts));
	if($symbol == 'xxx') {
		return 'Please add a coin symbol to fetch its data. For example, [coin_market_cap_dominance symbol="BTC"].';
	} else {
		$data = json_decode(scs_get_coin_info($symbol));
		return round($data->data->$symbol->quote->USD->market_cap_dominance, 3) . '%';
	}
}