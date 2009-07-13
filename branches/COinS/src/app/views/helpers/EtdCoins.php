<?
class Zend_View_Helper_EtdCoins {
  // values common to all
  private $class = "Z3988";
  private $version = "Z39.88-2004";
  private $format = "info:ofi/fmt:kev:mtx:dissertation";
   

    public function EtdCoins(etd $etd) {
      $values = array("ctx_ver" => $this->version,
		      "rft_val_fmt" => $this->format,		      
		      "rft.title" => $etd->title(),
		      "rft.author" => $etd->author(),
		      "rft.aulast" => $etd->mods->author->last,
		      "rft.aufirst" => $etd->mods->author->first,
		      "rft.date" => $etd->pubdate(),	// correct format?
		      "rft.inst" => $etd->mods->degree_grantor,
		      "rft.degree" => $etd->mods->degree,
		      "rft.id" => $etd->ark()
		      );
      

      return '<span class="' . $this->class . '"
	  title="' . urlencode(implode("&", $values) . '"> </span>';
    }
}