<?
/**
 * view helper to output COinS citation for an ETD
 *
 * @category Etd
 * @package Etd_View_Helpers
 */

class Zend_View_Helper_EtdCoins {
  // values common to all citations
  private $class = "Z3988";
  private $version = "Z39.88-2004";
  private $format = "info:ofi/fmt:kev:mtx:dissertation";
  private $country_code = "US";
  private $country = "United States";

    public function EtdCoins(etd $etd) {
      $values = array("ctx_ver=" . $this->version,
		      "rft_val_fmt=" . $this->format,		      
		      "rft.title=" . urlencode($etd->mods->title), // non-formatted title
		      "rft.au=" . urlencode($etd->author()),
		      "rft.aulast=" . urlencode($etd->mods->author->last),
		      "rft.aufirst=" . urlencode($etd->mods->author->first),
		      // FIXME: correct format? should we only use year?
		      "rft.inst=" . urlencode($etd->mods->degree_grantor->namePart),
		      "rft.date=" . urlencode($etd->pubdate()),	
		      "rft.cc=" . urlencode($this->country_code),
		      "rft.co=" . urlencode($this->country),
		      "rft.degree=" . urlencode($etd->mods->degree),
		      "rft.identifier=" . urlencode($etd->ark()),
		      "rft.tpages=" . urlencode($etd->mods->pages),	// total # pages
		      "rft.language=" . urlencode($etd->language()),
		      "rft.rights=" . urlencode($etd->mods->rights),
		      "rft.description=". urlencode($etd->mods->abstract),  //non-formatted version
		      );

      // could be multiple committe chairs... arg, spec may only allow one?
      foreach ($etd->mods->chair as $advisor) {
	$values[] = "rft.advisor=" . urlencode($advisor->full);
      }

      return '<span class="' . $this->class . '"
	  title="' . implode("&amp;", $values) . '"> </span>';
    }
}