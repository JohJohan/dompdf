<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
namespace Dompdf\FrameDecorator;

use Dompdf\Dompdf;
use Dompdf\Frame;

/**
 * Decorates Frames for table row layout
 *
 * @package dompdf
 */
class TableRow extends AbstractFrameDecorator
{
    /**
     * TableRow constructor.
     * @param Frame $frame
     * @param Dompdf $dompdf
     */
    function __construct(Frame $frame, Dompdf $dompdf)
    {
        parent::__construct($frame, $dompdf);
    }

    public function normalize(): void
    {
        /** @var AbstractFrameDecorator[] */
        $children = iterator_to_array($this->get_children());
        $td = null;

        foreach ($children as $child) {
            $display = $child->get_style()->display;

            if ($display === "table-cell") {
                // Reset anonymous td
                $td = null;
                continue;
            }

            // Remove empty text nodes between valid children
            if ($this->isEmptyTextNode($child)) {
                $this->remove_child($child);
                continue;
            }

            // Catch consecutive misplaced frames within a single anonymous cell
            if ($td === null) {
                $td = $this->createAnonymousChild("td", "table-cell");
                $this->insert_child_before($td, $child);
            }

            $td->append_child($child);
        }

        // Handle empty row: Make sure there is at least one cell
        if (!$this->get_first_child()) {
            $td = $this->createAnonymousChild("td", "table-cell");
            $this->append_child($td);
        }

        foreach ($this->get_children() as $child) {
            $child->normalize();
        }
    }
}
