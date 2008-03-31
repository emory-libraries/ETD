<?

/* country name by two-letter code
   modified code from http://www.phptutorial.info/iptocountry/the_script.html

   Used for access statistics
*/

class CountryNames {
 public $AD = "Andorra";
 public $AE = "United Arab Emirates";
 public $AF = "Afghanistan";
 public $AG = "Antigua And Barbuda";
 public $AI = "Anguilla";
 public $AL = "Albania";
 public $AM = "Armenia";
 public $AN = "Netherlands Antilles";
 public $AO = "Angola";
 public $AQ = "Antarctica";
 public $AR = "Argentina";
 public $AS = "American Samoa";
 public $AT = "Austria";
 public $AU = "Australia";
 public $AW = "Aruba";
 public $AZ = "Azerbaijan";
 public $BA = "Bosnia And Herzegovina";
 public $BB = "Barbados";
 public $BD = "Bangladesh";
 public $BE = "Belgium";
 public $BF = "Burkina Faso";
 public $BG = "Bulgaria";
 public $BH = "Bahrain";
 public $BI = "Burundi";
 public $BJ = "Benin";
 public $BM = "Bermuda";
 public $BN = "Brunei Darussalam";
 public $BO = "Bolivia";
 public $BR = "Brazil";
 public $BS = "Bahamas";
 public $BT = "Bhutan";
 public $BW = "Botswana";
 public $BY = "Belarus";
 public $BZ = "Belize";
 public $CA = "Canada";
 public $CD = "The Democratic Republic Of The Congo";
 public $CF = "Central African Republic";
 public $CG = "Congo";
 public $CH = "Switzerland";
 public $CI = "Cote D'ivoire";
 public $CK = "Cook Islands";
 public $CL = "Chile";
 public $CM = "Cameroon";
 public $CN = "China";
 public $CO = "Colombia";
 public $CR = "Costa Rica";
 public $CS = "Serbia And Montenegro";
 public $CU = "Cuba";
 public $CV = "Cape Verde";
 public $CY = "Cyprus";
 public $CZ = "Czech Republic";
 public $DE = "Germany";
 public $DJ = "Djibouti";
 public $DK = "Denmark";
 public $DM = "Dominica";
 public $DO = "Dominican Republic";
 public $DZ = "Algeria";
 public $EC = "Ecuador";
 public $EE = "Estonia";
 public $EG = "Egypt";
 public $ER = "Eritrea";
 public $ES = "Spain";
 public $ET = "Ethiopia";
 public $EU = "European Union";
 public $FI = "Finland";
 public $FJ = "Fiji";
 public $FK = "Falkland Islands (Malvinas)";
 public $FM = "Federated States Of Micronesia";
 public $FO = "Faroe Islands";
 public $FR = "France";
 public $GA = "Gabon";
 public $GB = "United Kingdom";
 public $GD = "Grenada";
 public $GE = "Georgia";
 public $GF = "French Guiana";
 public $GH = "Ghana";
 public $GI = "Gibraltar";
 public $GL = "Greenland";
 public $GM = "Gambia";
 public $GN = "Guinea";
 public $GP = "Guadeloupe";
 public $GQ = "Equatorial Guinea";
 public $GR = "Greece";
 public $GS = "South Georgia And The South Sandwich Islands";
 public $GT = "Guatemala";
 public $GU = "Guam";
 public $GW = "Guinea-Bissau";
 public $GY = "Guyana";
 public $HK = "Hong Kong";
 public $HN = "Honduras";
 public $HR = "Croatia";
 public $HT = "Haiti";
 public $HU = "Hungary";
 public $ID = "Indonesia";
 public $IE = "Ireland";
 public $IL = "Israel";
 public $IN = "India";
 public $IO = "British Indian Ocean Territory";
 public $IQ = "Iraq";
 public $IR = "Islamic Republic Of Iran";
 public $IS = "Iceland";
 public $IT = "Italy";
 public $JM = "Jamaica";
 public $JO = "Jordan";
 public $JP = "Japan";
 public $KE = "Kenya";
 public $KG = "Kyrgyzstan";
 public $KH = "Cambodia";
 public $KI = "Kiribati";
 public $KM = "Comoros";
 public $KN = "Saint Kitts And Nevis";
 public $KR = "Republic Of Korea";
 public $KW = "Kuwait";
 public $KY = "Cayman Islands";
 public $KZ = "Kazakhstan";
 public $LA = "Lao People's Democratic Republic";
 public $LB = "Lebanon";
 public $LC = "Saint Lucia";
 public $LI = "Liechtenstein";
 public $LK = "Sri Lanka";
 public $LR = "Liberia";
 public $LS = "Lesotho";
 public $LT = "Lithuania";
 public $LU = "Luxembourg";
 public $LV = "Latvia";
 public $LY = "Libyan Arab Jamahiriya";
 public $MA = "Morocco";
 public $MC = "Monaco";
 public $MD = "Republic Of Moldova";
 public $MG = "Madagascar";
 public $MH = "Marshall Islands";
 public $MK = "The Former Yugoslav Republic Of Macedonia";
 public $ML = "Mali";
 public $MM = "Myanmar";
 public $MN = "Mongolia";
 public $MO = "Macao";
 public $MP = "Northern Mariana Islands";
 public $MQ = "Martinique";
 public $MR = "Mauritania";
 public $MT = "Malta";
 public $MU = "Mauritius";
 public $MV = "Maldives";
 public $MW = "Malawi";
 public $MX = "Mexico";
 public $MY = "Malaysia";
 public $MZ = "Mozambique";
 public $NA = "Namibia";
 public $NC = "New Caledonia";
 public $NE = "Niger";
 public $NF = "Norfolk Island";
 public $NG = "Nigeria";
 public $NI = "Nicaragua";
 public $NL = "Netherlands";
 public $NO = "Norway";
 public $NP = "Nepal";
 public $NR = "Nauru";
 public $NU = "Niue";
 public $NZ = "New Zealand";
 public $OM = "Oman";
 public $PA = "Panama";
 public $PE = "Peru";
 public $PF = "French Polynesia";
 public $PG = "Papua New Guinea";
 public $PH = "Philippines";
 public $PK = "Pakistan";
 public $PL = "Poland";
 public $PR = "Puerto Rico";
 public $PS = "Palestinian Territory";
 public $PT = "Portugal";
 public $PW = "Palau";
 public $PY = "Paraguay";
 public $QA = "Qatar";
 public $RE = "Reunion";
 public $RO = "Romania";
 public $RU = "Russian Federation";
 public $RW = "Rwanda";
 public $SA = "Saudi Arabia";
 public $SB = "Solomon Islands";
 public $SC = "Seychelles";
 public $SD = "Sudan";
 public $SE = "Sweden";
 public $SG = "Singapore";
 public $SI = "Slovenia";
 public $SK = "Slovakia (Slovak Republic)";
 public $SL = "Sierra Leone";
 public $SM = "San Marino";
 public $SN = "Senegal";
 public $SO = "Somalia";
 public $SR = "Suriname";
 public $ST = "Sao Tome And Principe";
 public $SV = "El Salvador";
 public $SY = "Syrian Arab Republic";
 public $SZ = "Swaziland";
 public $TD = "Chad";
 public $TF = "French Southern Territories";
 public $TG = "Togo";
 public $TH = "Thailand";
 public $TJ = "Tajikistan";
 public $TK = "Tokelau";
 public $TL = "Timor-Leste";
 public $TM = "Turkmenistan";
 public $TN = "Tunisia";
 public $TO = "Tonga";
 public $TR = "Turkey";
 public $TT = "Trinidad And Tobago";
 public $TV = "Tuvalu";
 public $TW = "Taiwan Province Of China";
 public $TZ = "United Republic Of Tanzania";
 public $UA = "Ukraine";
 public $UG = "Uganda";
 public $US = "United States";
 public $UY = "Uruguay";
 public $UZ = "Uzbekistan";
 public $VA = "Holy See (Vatican City State)";
 public $VC = "Saint Vincent And The Grenadines";
 public $VE = "Venezuela";
 public $VG = "Virgin Islands";
 public $VI = "Virgin Islands";
 public $VN = "Viet Nam";
 public $VU = "Vanuatu";
 public $WS = "Samoa";
 public $YE = "Yemen";
 public $YT = "Mayotte";
 public $YU = "Serbia And Montenegro (Formally Yugoslavia)";
 public $ZA = "South Africa";
 public $ZM = "Zambia";
 public $ZW = "Zimbabwe";
 public $ZZ = "Reserved";

 public function __get($name) {
   return "(unknown)";
 }
  }
?>
