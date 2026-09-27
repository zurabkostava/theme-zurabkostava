<?php
/**
 * Language registry and URL helpers.
 *
 * English remains the source language. Existing Georgian metadata and URLs keep
 * their current keys so this layer can be introduced without a migration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function zk_get_default_languages() {
    return array(
        'en' => array(
            'code'       => 'en',
            'name'       => 'English',
            'native_name'=> 'English',
            'locale'     => 'en-US',
            'prefix'     => '',
            'enabled'    => true,
            'source'     => true,
        ),
        'ka' => array(
            'code'       => 'ka',
            'name'       => 'Georgian',
            'native_name'=> 'ქართული',
            'locale'     => 'ka-GE',
            'prefix'     => 'ka',
            'enabled'    => true,
            'source'     => false,
        ),
    );
}

function zk_sanitize_language_definition( $language, $code = '' ) {
    $code = strtolower( sanitize_key( $code ?: ( isset( $language['code'] ) ? $language['code'] : '' ) ) );
    if ( ! preg_match( '/^[a-z]{2,3}$/', $code ) ) {
        return null;
    }

    $prefix = isset( $language['prefix'] ) ? strtolower( sanitize_title( $language['prefix'] ) ) : $code;
    $locale = isset( $language['locale'] ) ? sanitize_text_field( $language['locale'] ) : $code;
    if ( ! preg_match( '/^[A-Za-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/', $locale ) ) {
        $locale = $code;
    }

    return array(
        'code'        => $code,
        'name'        => sanitize_text_field( isset( $language['name'] ) ? $language['name'] : strtoupper( $code ) ),
        'native_name' => sanitize_text_field( isset( $language['native_name'] ) ? $language['native_name'] : strtoupper( $code ) ),
        'locale'      => str_replace( '_', '-', $locale ),
        'prefix'      => 'en' === $code ? '' : ( $prefix ?: $code ),
        'enabled'     => ! empty( $language['enabled'] ),
        'source'      => 'en' === $code,
    );
}

function zk_get_languages( $enabled_only = true ) {
    $defaults = zk_get_default_languages();
    $saved    = get_option( 'zk_languages_v1', array() );
    $languages = $defaults;

    if ( is_array( $saved ) ) {
        foreach ( $saved as $code => $language ) {
            $clean = zk_sanitize_language_definition( $language, $code );
            if ( $clean ) {
                $languages[ $clean['code'] ] = $clean;
            }
        }
    }

    // The source language and the existing Georgian site cannot be disabled by
    // an incomplete option value.
    $languages['en'] = array_merge( $defaults['en'], isset( $languages['en'] ) ? $languages['en'] : array(), array( 'enabled' => true, 'source' => true, 'prefix' => '' ) );
    $languages['ka'] = array_merge( $defaults['ka'], isset( $languages['ka'] ) ? $languages['ka'] : array() );

    $used_prefixes = array();
    foreach ( $languages as $code => &$language ) {
        if ( 'en' === $code ) continue;
        $prefix = trim( $language['prefix'], '/' );
        if ( ! $prefix || isset( $used_prefixes[ $prefix ] ) ) {
            $prefix = $code;
        }
        $language['prefix'] = $prefix;
        $used_prefixes[ $prefix ] = true;
    }
    unset( $language );

    if ( $enabled_only ) {
        $languages = array_filter( $languages, function( $language ) {
            return ! empty( $language['enabled'] );
        } );
    }

    return $languages;
}

function zk_get_language( $code ) {
    $languages = zk_get_languages( false );
    return isset( $languages[ $code ] ) ? $languages[ $code ] : $languages['en'];
}

function zk_get_translatable_languages( $enabled_only = true ) {
    $languages = zk_get_languages( $enabled_only );
    unset( $languages['en'] );
    return $languages;
}

function zk_language_meta_key( $field, $code ) {
    return '_zk_' . sanitize_key( $field ) . '_' . sanitize_key( $code );
}

function zk_detect_language_from_path( $path ) {
    $path = '/' . ltrim( (string) $path, '/' );
    foreach ( zk_get_translatable_languages() as $code => $language ) {
        $prefix = trim( $language['prefix'], '/' );
        if ( $prefix && ( $path === '/' . $prefix || 0 === strpos( $path, '/' . $prefix . '/' ) ) ) {
            return $code;
        }
    }
    return 'en';
}

function zk_strip_language_prefix( $path ) {
    $path = '/' . ltrim( (string) $path, '/' );
    foreach ( zk_get_translatable_languages() as $language ) {
        $prefix = trim( $language['prefix'], '/' );
        if ( $prefix && ( $path === '/' . $prefix || 0 === strpos( $path, '/' . $prefix . '/' ) ) ) {
            $path = substr( $path, strlen( $prefix ) + 1 );
            return $path ? '/' . ltrim( $path, '/' ) : '/';
        }
    }
    return $path ?: '/';
}

function zk_get_language_path( $path, $code ) {
    $base = zk_strip_language_prefix( $path );
    if ( 'en' === $code ) {
        return $base;
    }

    $language = zk_get_language( $code );
    $prefix   = trim( $language['prefix'], '/' );
    return '/' === $base ? '/' . $prefix . '/' : '/' . $prefix . $base;
}

