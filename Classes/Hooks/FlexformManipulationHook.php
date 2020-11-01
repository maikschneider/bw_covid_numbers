<?php

namespace Blueways\BwCovidNumbers\Hooks;

class FlexformManipulationHook
{

    /**
     * Override color picker for v7
     *
     * @param array $dataStructArray
     * @param array $conf
     * @param array $row
     * @param string $table
     */
    public function getFlexFormDS_postProcessDS(&$dataStructArray, $conf, $row, $table)
    {
        if ($table === 'tt_content' && $row['CType'] === 'list' && $row['list_type'] === 'bwcovidnumbers_pi1') {
            $v7ColorPicker = [
                'type' => 'input',
                'size' => '10',
                'default' => '#000000',
                'eval' => 'trim',
                'wizards' => [
                    'colorChoice' => [
                        'type' => 'colorbox',
                        'title' => 'LLL:EXT:lang/locallang_wizards:colorpicker_title',
                        'module' => [
                            'name' => 'wizard_colorpicker'
                        ],
                        'dim' => '20x20',
                        'JSopenParams' => 'height=750,width=380,status=1,menubar=1,scrollbars=1',
                        'exampleImg' => 'EXT:bw_covid_numbers/Resources/Public/Images/colorpicker.png'
                    ]
                ]
            ];

            $dataStructArray['sheets']['general']['ROOT']['el']['settings.graphs']['el']['state']['el']['color']['TCEforms']['config'] = $v7ColorPicker;
            $dataStructArray['sheets']['general']['ROOT']['el']['settings.graphs']['el']['district']['el']['color']['TCEforms']['config'] = $v7ColorPicker;
        }
    }
}
