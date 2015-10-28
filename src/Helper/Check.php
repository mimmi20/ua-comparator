<?php
/**
 * Copyright (c) 2015, Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  UaComparator
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Helper;

use UaComparator\Module\ModuleCollection;

/**
 * Class Check
 *
 * @package UaComparator\Helper
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
class Check
{
    const MINIMUM = 1;
    const MEDIUM  = 2;

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param int $checklevel
     *
     * @return array
     *
     */
    public function getChecks($checklevel)
    {
        $checks = array(
            'Browser'               => array(
                'key'         => 'mobile_browser',
            ),
            'Browser Version'       => array(
                'key'         => 'mobile_browser_version',
            ),
            'Browser Modus'         => array(
                'key'         => 'mobile_browser_modus',
            ),
            'Browser Bits'          => array(
                'key'         => 'mobile_browser_bits',
            ),
            'Browser Typ'           => array(
                'key'         => 'browser_type',
            ),
            'Browser Hersteller'    => array(
                'key'         => 'mobile_browser_manufacturer',
            ),
            'Engine'                => array(
                'key'         => 'renderingengine_name',
            ),
            'Engine Version'        => array(
                'key'         => 'renderingengine_version',
            ),
            'Engine Hersteller'     => array(
                'key'         => 'renderingengine_manufacturer',
            ),
            'OS'                    => array(
                'key'         => 'device_os',
            ),
            'OS Version'            => array(
                'key'         => 'device_os_version',
            ),
            'OS Bits'               => array(
                'key'         => 'device_os_bits',
            ),
            'OS Hersteller'         => array(
                'key'         => 'device_os_manufacturer',
            ),
            'Device Brand Name'     => array(
                'key'         => 'brand_name',
            ),
            'Device Marketing Name' => array(
                'key'         => 'marketing_name',
            ),
            'Device Model Name'     => array(
                'key'         => 'model_name',
            ),
            'Device Hersteller'     => array(
                'key'         => 'manufacturer_name',
            ),
            'Device Typ'            => array(
                'key'         => 'device_type',
            ),
            'Desktop'               => array(
                'key'         => array('isDesktop'),
            ),
            'TV'                    => array(
                'key'         => array('isTvDevice'),
            ),
            'Mobile'                => array(
                'key'         => array('isMobileDevice'),
            ),
            'Tablet'                => array(
                'key'         => array('isTablet'),
            ),
            'Bot'                   => array(
                'key'         => array('isCrawler'),
            ),
            'Console'               => array(
                'key'         => array('isConsole'),
            ),
            'Transcoder'            => array(
                'key'         => 'is_transcoder',
            ),
            'Syndication-Reader'    => array(
                'key'         => 'is_syndication_reader',
            ),
            'pointing_method'       => array(
                'key'         => 'pointing_method',
            ),
            'has_qwerty_keyboard'   => array(
                'key'         => 'has_qwerty_keyboard',
            ),
            // display
            'resolution_width'      => array(
                'key'         => 'resolution_width',
            ),
            'resolution_height'     => array(
                'key'         => 'resolution_height',
            ),
            'dual_orientation'      => array(
                'key'         => 'dual_orientation',
            ),
            'colors'                => array(
                'key'         => 'colors',
            ),
            'wurflKey'              => array(
                'key'         => 'wurflKey',
            ),
        );

        if ($checklevel == self::MEDIUM) {
            $checks += array(
                // product info
                'can_skip_aligned_link_row'                      => array(
                    'key'         => 'can_skip_aligned_link_row',
                ),
                'device_claims_web_support'                      => array(
                    'key'         => 'device_claims_web_support',
                ),
                'can_assign_phone_number'                        => array(
                    'key'         => 'can_assign_phone_number',
                ),
                'nokia_feature_pack'                             => array(
                    'key'         => 'nokia_feature_pack',
                ),
                'nokia_series'                                   => array(
                    'key'         => 'nokia_series',
                ),
                'nokia_edition'                                  => array(
                    'key'         => 'nokia_edition',
                ),
                'ununiqueness_handler'                           => array(
                    'key'         => 'ununiqueness_handler',
                ),
                'uaprof'                                         => array(
                    'key'         => 'uaprof',
                ),
                'uaprof2'                                        => array(
                    'key'         => 'uaprof2',
                ),
                'uaprof3'                                        => array(
                    'key'         => 'uaprof3',
                ),
                'unique'                                         => array(
                    'key'         => 'unique',
                ),
                'model_extra_info'                               => array(
                    'key'         => 'model_extra_info',
                ),
                // display
                'physical_screen_width'                          => array(
                    'key'         => 'physical_screen_width',
                ),
                'physical_screen_height'                         => array(
                    'key'         => 'physical_screen_height',
                ),
                'columns'                                        => array(
                    'key'         => 'columns',
                ),
                'rows'                                           => array(
                    'key'         => 'rows',
                ),
                'max_image_width'                                => array(
                    'key'         => 'max_image_width',
                ),
                'max_image_height'                               => array(
                    'key'         => 'max_image_height',
                ),
                // markup
                'utf8_support'                                   => array(
                    'key'         => 'utf8_support',
                ),
                'multipart_support'                              => array(
                    'key'         => 'multipart_support',
                ),
                'supports_background_sounds'                     => array(
                    'key'         => 'supports_background_sounds',
                ),
                'supports_vb_script'                             => array(
                    'key'         => 'supports_vb_script',
                ),
                'supports_java_applets'                          => array(
                    'key'         => 'supports_java_applets',
                ),
                'supports_activex_controls'                      => array(
                    'key'         => 'supports_activex_controls',
                ),
                'preferred_markup'                               => array(
                    'key'         => 'preferred_markup',
                ),
                'html_web_3_2'                                   => array(
                    'key'         => 'html_web_3_2',
                ),
                'html_web_4_0'                                   => array(
                    'key'         => 'html_web_4_0',
                ),
                'html_wi_oma_xhtmlmp_1_0'                        => array(
                    'key'         => 'html_wi_oma_xhtmlmp_1_0',
                ),
                'wml_1_1'                                        => array(
                    'key'         => 'wml_1_1',
                ),
                'wml_1_2'                                        => array(
                    'key'         => 'wml_1_2',
                ),
                'wml_1_3'                                        => array(
                    'key'         => 'wml_1_3',
                ),
                'xhtml_support_level'                            => array(
                    'key'         => 'xhtml_support_level',
                ),
                'html_wi_imode_html_1'                           => array(
                    'key'         => 'html_wi_imode_html_1',
                ),
                'html_wi_imode_html_2'                           => array(
                    'key'         => 'html_wi_imode_html_2',
                ),
                'html_wi_imode_html_3'                           => array(
                    'key'         => 'html_wi_imode_html_3',
                ),
                'html_wi_imode_html_4'                           => array(
                    'key'         => 'html_wi_imode_html_4',
                ),
                'html_wi_imode_html_5'                           => array(
                    'key'         => 'html_wi_imode_html_5',
                ),
                'html_wi_imode_htmlx_1'                          => array(
                    'key'         => 'html_wi_imode_htmlx_1',
                ),
                'html_wi_imode_htmlx_1_1'                        => array(
                    'key'         => 'html_wi_imode_htmlx_1_1',
                ),
                'html_wi_w3_xhtmlbasic'                          => array(
                    'key'         => 'html_wi_w3_xhtmlbasic',
                ),
                'html_wi_imode_compact_generic'                  => array(
                    'key'         => 'html_wi_imode_compact_generic',
                ),
                'voicexml'                                       => array(
                    'key'         => 'voicexml',
                ),
                // chtml
                'chtml_table_support'                            => array(
                    'key'         => 'chtml_table_support',
                ),
                'imode_region'                                   => array(
                    'key'         => 'imode_region',
                ),
                'chtml_can_display_images_and_text_on_same_line' => array(
                    'key'         => 'chtml_can_display_images_and_text_on_same_line',
                ),
                'chtml_displays_image_in_center'                 => array(
                    'key'         => 'chtml_displays_image_in_center',
                ),
                'chtml_make_phone_call_string'                   => array(
                    'key'         => 'chtml_make_phone_call_string',
                ),
                'emoji'                                          => array(
                    'key'         => 'emoji',
                ),
                // xhtml
                'xhtml_select_as_radiobutton'                    => array(
                    'key'         => 'xhtml_select_as_radiobutton',
                ),
                'xhtml_avoid_accesskeys'                         => array(
                    'key'         => 'xhtml_avoid_accesskeys',
                ),
                'xhtml_select_as_dropdown'                       => array(
                    'key'         => 'xhtml_select_as_dropdown',
                ),
                'xhtml_supports_iframe'                          => array(
                    'key'         => 'xhtml_supports_iframe',
                ),
                'xhtml_supports_forms_in_table'                  => array(
                    'key'         => 'xhtml_supports_forms_in_table',
                ),
                'xhtmlmp_preferred_mime_type'                    => array(
                    'key'         => 'xhtmlmp_preferred_mime_type',
                ),
                'xhtml_select_as_popup'                          => array(
                    'key'         => 'xhtml_select_as_popup',
                ),
                'xhtml_honors_bgcolor'                           => array(
                    'key'         => 'xhtml_honors_bgcolor',
                ),
                'xhtml_file_upload'                              => array(
                    'key'         => 'xhtml_file_upload',
                ),
                'xhtml_preferred_charset'                        => array(
                    'key'         => 'xhtml_preferred_charset',
                ),
                'xhtml_supports_css_cell_table_coloring'         => array(
                    'key'         => 'xhtml_supports_css_cell_table_coloring',
                ),
                'xhtml_autoexpand_select'                        => array(
                    'key'         => 'xhtml_autoexpand_select',
                ),
                'accept_third_party_cookie'                      => array(
                    'key'         => 'accept_third_party_cookie',
                ),
                'xhtml_make_phone_call_string'                   => array(
                    'key'         => 'xhtml_make_phone_call_string',
                ),
                'xhtml_allows_disabled_form_elements'            => array(
                    'key'         => 'xhtml_allows_disabled_form_elements',
                ),
                'xhtml_supports_invisible_text'                  => array(
                    'key'         => 'xhtml_supports_invisible_text',
                ),
                'cookie_support'                                 => array(
                    'key'         => 'cookie_support',
                ),
                'xhtml_send_mms_string'                          => array(
                    'key'         => 'xhtml_send_mms_string',
                ),
                'xhtml_table_support'                            => array(
                    'key'         => 'xhtml_table_support',
                ),
                'xhtml_display_accesskey'                        => array(
                    'key'         => 'xhtml_display_accesskey',
                ),
                'xhtml_can_embed_video'                          => array(
                    'key'         => 'xhtml_can_embed_video',
                ),
                'xhtml_supports_monospace_font'                  => array(
                    'key'         => 'xhtml_supports_monospace_font',
                ),
                'xhtml_supports_inline_input'                    => array(
                    'key'         => 'xhtml_supports_inline_input',
                ),
                'xhtml_document_title_support'                   => array(
                    'key'         => 'xhtml_document_title_support',
                ),
                'xhtml_support_wml2_namespace'                   => array(
                    'key'         => 'xhtml_support_wml2_namespace',
                ),
                'xhtml_readable_background_color1'               => array(
                    'key'         => 'xhtml_readable_background_color1',
                ),
                'xhtml_format_as_attribute'                      => array(
                    'key'         => 'xhtml_format_as_attribute',
                ),
                'xhtml_supports_table_for_layout'                => array(
                    'key'         => 'xhtml_supports_table_for_layout',
                ),
                'xhtml_readable_background_color2'               => array(
                    'key'         => 'xhtml_readable_background_color2',
                ),
                'xhtml_send_sms_string'                          => array(
                    'key'         => 'xhtml_send_sms_string',
                ),
                'xhtml_format_as_css_property'                   => array(
                    'key'         => 'xhtml_format_as_css_property',
                ),
                'opwv_xhtml_extensions_support'                  => array(
                    'key'         => 'opwv_xhtml_extensions_support',
                ),
                'xhtml_marquee_as_css_property'                  => array(
                    'key'         => 'xhtml_marquee_as_css_property',
                ),
                'xhtml_nowrap_mode'                              => array(
                    'key'         => 'xhtml_nowrap_mode',
                ),
                // image format
                'jpg'                                            => array(
                    'key'         => 'jpg',
                ),
                'gif'                                            => array(
                    'key'         => 'gif',
                ),
                'bmp'                                            => array(
                    'key'         => 'bmp',
                ),
                'wbmp'                                           => array(
                    'key'         => 'wbmp',
                ),
                'gif_animated'                                   => array(
                    'key'         => 'gif_animated',
                ),
                'png'                                            => array(
                    'key'         => 'png',
                ),
                'greyscale'                                      => array(
                    'key'         => 'greyscale',
                ),
                'transparent_png_index'                          => array(
                    'key'         => 'transparent_png_index',
                ),
                'epoc_bmp'                                       => array(
                    'key'         => 'epoc_bmp',
                ),
                'svgt_1_1_plus'                                  => array(
                    'key'         => 'svgt_1_1_plus',
                ),
                'svgt_1_1'                                       => array(
                    'key'         => 'svgt_1_1',
                ),
                'transparent_png_alpha'                          => array(
                    'key'         => 'transparent_png_alpha',
                ),
                'tiff'                                           => array(
                    'key'         => 'tiff',
                ),
                // security
                'https_support'                                  => array(
                    'key'         => 'https_support',
                ),
                // storage
                'max_url_length_bookmark'                        => array(
                    'key'         => 'max_url_length_bookmark',
                ),
                'max_url_length_cached_page'                     => array(
                    'key'         => 'max_url_length_cached_page',
                ),
                'max_url_length_in_requests'                     => array(
                    'key'         => 'max_url_length_in_requests',
                ),
                'max_url_length_homepage'                        => array(
                    'key'         => 'max_url_length_homepage',
                ),
                // ajax
                'ajax_support_getelementbyid'                    => array(
                    'key'         => 'ajax_support_getelementbyid',
                ),
                'ajax_xhr_type'                                  => array(
                    'key'         => 'ajax_xhr_type',
                ),
                'ajax_support_event_listener'                    => array(
                    'key'         => 'ajax_support_event_listener',
                ),
                'ajax_support_javascript'                        => array(
                    'key'         => 'ajax_support_javascript',
                ),
                'ajax_manipulate_dom'                            => array(
                    'key'         => 'ajax_manipulate_dom',
                ),
                'ajax_support_inner_html'                        => array(
                    'key'         => 'ajax_support_inner_html',
                ),
                'ajax_manipulate_css'                            => array(
                    'key'         => 'ajax_manipulate_css',
                ),
                'ajax_support_events'                            => array(
                    'key'         => 'ajax_support_events',
                ),
                'ajax_preferred_geoloc_api'                      => array(
                    'key'         => 'ajax_preferred_geoloc_api',
                ),
                // pdf
                'pdf_support'                                    => array(
                    'key'         => 'pdf_support',
                ),
                // third_party
                'jqm_grade'                                      => array(
                    'key'         => 'jqm_grade',
                ),
                'is_sencha_touch_ok'                             => array(
                    'key'         => 'is_sencha_touch_ok',
                ),
                // html
                'image_inlining'                                 => array(
                    'key'         => 'image_inlining',
                ),
                'canvas_support'                                 => array(
                    'key'         => 'canvas_support',
                ),
                'viewport_width'                                 => array(
                    'key'         => 'viewport_width',
                ),
                'html_preferred_dtd'                             => array(
                    'key'         => 'html_preferred_dtd',
                ),
                'viewport_supported'                             => array(
                    'key'         => 'viewport_supported',
                ),
                'viewport_minimum_scale'                         => array(
                    'key'         => 'viewport_minimum_scale',
                ),
                'viewport_initial_scale'                         => array(
                    'key'         => 'viewport_initial_scale',
                ),
                'mobileoptimized'                                => array(
                    'key'         => 'mobileoptimized',
                ),
                'viewport_maximum_scale'                         => array(
                    'key'         => 'viewport_maximum_scale',
                ),
                'viewport_userscalable'                          => array(
                    'key'         => 'viewport_userscalable',
                ),
                'handheldfriendly'                               => array(
                    'key'         => 'handheldfriendly',
                ),
                // css
                'css_spriting'                                   => array(
                    'key'         => 'css_spriting',
                ),
                'css_gradient'                                   => array(
                    'key'         => 'css_gradient',
                ),
                'css_gradient_linear'                            => array(
                    'key'         => 'css_gradient_linear',
                ),
                'css_border_image'                               => array(
                    'key'         => 'css_border_image',
                ),
                'css_rounded_corners'                            => array(
                    'key'         => 'css_rounded_corners',
                ),
                'css_supports_width_as_percentage'               => array(
                    'key'         => 'css_supports_width_as_percentage',
                ),
                // bugs
                'empty_option_value_support'                     => array(
                    'key'         => 'empty_option_value_support',
                ),
                'basic_authentication_support'                   => array(
                    'key'         => 'basic_authentication_support',
                ),
                'post_method_support'                            => array(
                    'key'         => 'post_method_support',
                ),
                // rss
                'rss_support'                                    => array(
                    'key'         => 'rss_support',
                ),
                // sms
                'sms_enabled'                                    => array(
                    'key'         => 'sms_enabled',
                ),
                // chips
                'nfc_support'                                    => array(
                    'key'         => 'nfc_support',
                ),
            );
        }

        return $checks;
    }
}
