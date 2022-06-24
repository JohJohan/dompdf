<?php
/**
 * @package dompdf
 * @link    http://dompdf.github.com/
 * @author  Benj Carson <benjcarson@digitaljunkies.ca>
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
namespace Dompdf\FrameDecorator;

use Dompdf\Cellmap;
use DOMNode;
use Dompdf\Css\Style;
use Dompdf\Dompdf;
use Dompdf\Frame;

/**
 * Decorates Frames for table layout
 *
 * @package dompdf
 */
class Table extends AbstractFrameDecorator
{
    public const VALID_CHILDREN = Style::TABLE_INTERNAL_TYPES;

    /**
     * List of all row-group display types.
     */
    public const ROW_GROUPS = [
        "table-row-group",
        "table-header-group",
        "table-footer-group"
    ];

    /**
     * The Cellmap object for this table.  The cellmap maps table cells
     * to rows and columns, and aids in calculating column widths.
     *
     * @var Cellmap
     */
    protected $_cellmap;

    /**
     * Table header rows.  Each table header is duplicated when a table
     * spans pages.
     *
     * @var TableRowGroup[]
     */
    protected $_headers;

    /**
     * Table footer rows.  Each table footer is duplicated when a table
     * spans pages.
     *
     * @var TableRowGroup[]
     */
    protected $_footers;

    /**
     * Class constructor
     *
     * @param Frame $frame the frame to decorate
     * @param Dompdf $dompdf
     */
    public function __construct(Frame $frame, Dompdf $dompdf)
    {
        parent::__construct($frame, $dompdf);
        $this->_cellmap = new Cellmap($this);

        if ($frame->get_style()->table_layout === "fixed") {
            $this->_cellmap->set_layout_fixed(true);
        }

        $this->_headers = [];
        $this->_footers = [];
    }

    public function reset()
    {
        parent::reset();
        $this->_cellmap->reset();
        $this->_headers = [];
        $this->_footers = [];
        $this->_reflower->reset();
    }

    //........................................................................

    /**
     * Split the table at $row.  $row and all subsequent rows will be
     * added to the clone.  This method is overridden in order to remove
     * frames from the cellmap properly.
     */
    public function split(?Frame $child = null, bool $page_break = false, bool $forced = false): void
    {
        if (is_null($child)) {
            parent::split($child, $page_break, $forced);
            return;
        }

        // If $child is a header or if it is the first non-header row, do
        // not duplicate headers, simply move the table to the next page.
        if (count($this->_headers)
            && !in_array($child, $this->_headers, true)
            && !in_array($child->get_prev_sibling(), $this->_headers, true)
        ) {
            $first_header = null;

            // Insert copies of the table headers before $child
            foreach ($this->_headers as $header) {
                $new_header = $header->deep_copy();

                if (is_null($first_header)) {
                    $first_header = $new_header;
                }

                $this->insert_child_before($new_header, $child);
            }

            parent::split($first_header, $page_break, $forced);
        } else {
            parent::split($child, $page_break, $forced);
        }
    }

    public function copy(DOMNode $node)
    {
        $deco = parent::copy($node);

        // In order to keep columns' widths through pages
        $deco->_cellmap->set_columns($this->_cellmap->get_columns());
        $deco->_cellmap->lock_columns();

        return $deco;
    }

    /**
     * Return this table's Cellmap
     *
     * @return Cellmap
     */
    public function get_cellmap()
    {
        return $this->_cellmap;
    }

    //........................................................................

    /**
     * Restructure the subtree so that the table has the correct structure.
     *
     * Misplaced children are appropriately wrapped in anonymous row groups,
     * rows, and cells.
     *
     * https://www.w3.org/TR/CSS21/tables.html#anonymous-boxes
     */
    public function normalize(): void
    {
        $column_caption = ["table-column-group", "table-column", "table-caption"];
        /** @var AbstractFrameDecorator[] */
        $children = iterator_to_array($this->get_children());
        $tbody = null;

        foreach ($children as $child) {
            $display = $child->get_style()->display;

            if (in_array($display, self::ROW_GROUPS, true)) {
                // Reset anonymous tbody
                $tbody = null;

                // Add headers and footers
                if ($display === "table-header-group") {
                    $this->_headers[] = $child;
                } elseif ($display === "table-footer-group") {
                    $this->_footers[] = $child;
                }
                continue;
            }

            if (in_array($display, $column_caption, true)) {
                continue;
            }

            // Remove empty text nodes between valid children
            if ($this->isEmptyTextNode($child)) {
                $this->remove_child($child);
                continue;
            }

            // Catch consecutive misplaced frames within a single anonymous group
            if ($tbody === null) {
                $tbody = $this->createAnonymousChild("tbody", "table-row-group");
                $this->insert_child_before($tbody, $child);
            }

            $tbody->append_child($child);
        }

        // Handle empty table: Make sure there is at least one row group
        if (!$this->get_first_child()) {
            $tbody = $this->createAnonymousChild("tbody", "table-row-group");
            $this->append_child($tbody);
        }

        foreach ($this->get_children() as $child) {
            $child->normalize();
        }
    }
}
