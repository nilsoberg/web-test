

/**
 * Manages the color logic for a sunburst diagram.
 * The class calculates node colors based on their kingdom and depth in the hierarchy.
 *
 * The license and copyright for the HSV to RGB conversion logic can be found
 * below.
 * Copyright (c) 2010: Genome Research Ltd.
 * Authors: Rob Finn (rdf@sanger.ac.uk), John Tate (jt6@sanger.ac.uk)
 */
class SunburstColor {

    // Private fields for internal data and helper methods
    #maxDepth;
    #colors;
    #colorFn;

    /**
     * @param {number} [maxDepth=7] - The maximum depth of the tree, used for color gradient calculations.
     */
    constructor(maxDepth = 7) {
        this.#maxDepth = maxDepth;
        this.#colors = this.#getColorList();
        this.#colorFn = this.#createColorGradientFn();
    }

    /**
     * The main public method to get the color for a given data node.
     * @param {object} d - The data object for the node (e.g., { node: "Bacteria" }).
     * @returns {string} A CSS color string (e.g., "rgb(255,0,0)" or "gray").
     */
    getColorForNode(d, depth) {
        const kingdom = this.#getKingdom(d);

        // Return gray for the root or for unclassifiable kingdoms
        if (!kingdom || kingdom === "Root" || !this.#colors[kingdom]) {
            return "gray";
        }

        // Calculate and return the specific color based on kingdom and depth
        const color = this.#colorFn(this.#colors[kingdom], depth);
        return color;
    }

    /**
     * Determines the root-level kingdom for a given node.
     * @private
     * @param {object} d - The data object.
     * @returns {string} The name of the kingdom.
     */
    #getKingdom(d) {
        if (!d) return ""; // No graphics object, no kingdom
        if (!d.theParent) return d.node; // Top-level node is the kingdom

        // Traverse up the tree to find the node at depth 1, which represents the kingdom
        while (d.d > 1) {
            d = d.theParent;
        }
        return d.node;
    }

    /**
     * Creates a function that calculates a color within a specific hue range based on depth.
     * @private
     * @returns {function} A function that takes a color data object and depth to return a color string.
     */
    #createColorGradientFn() {
        const hsvToRgb = this.#hsvToRgb; // Cache the method for use in the returned function
        const maxDepth = this.#maxDepth;

        return function(colorData, depth) {
            const hMax = 2 * Math.PI;
            // Calculate hue based on the node's depth to create a gradient effect
            let h = (colorData.maxH - colorData.minH) * (1 - depth / maxDepth) + colorData.minH;
            h = h / hMax; // Normalize hue to the 0-1 range for hsvToRgb

            const s = 1; // Full saturation
            const v = 1; // Full value/brightness

            const rgb = hsvToRgb(h, s, v);
            return `rgb(${rgb.join(",")})`;
        };
    }

    /**
     * Provides the static color ranges for each major biological kingdom.
     * @private
     * @returns {object} An object mapping kingdom names to their hue ranges.
     */
    #getColorList() {
        return {
            "Archaea": { "minH": 0.017453292519943295, "maxH": 0.6981317007977318 },
            "Bacteria": { "minH": 0.7726646259971648, "maxH": 2.9179938779914944 },
            "unclassified sequences": { "minH": 3.141592653589793, "maxH": 3.1764992386296798 },
            "unclassified": { "minH": 3.193952531149623, "maxH": 3.2288591161895095 },
            "Eukaryota": { "minH": 4.363323129985824, "maxH": 5.235987755982989 },
            "Viruses": { "minH": 5.410520681182422, "maxH": 5.759586531581287 },
            "Viroids": { "minH": 5.777039824101231, "maxH": 5.846852994181004 },
            "other sequences": { "minH": 5.8643062867009474, "maxH": 6.19591884457987 }
        };
    }

    /**
     * Converts an HSV color value to RGB.
     * @private
     * @param {number} h - Hue (0-1).
     * @param {number} s - Saturation (0-1).
     * @param {number} v - Value (0-1).
     * @returns {Array<number>} An array containing the R, G, and B values (0-255).
     */
    #hsvToRgb(h, s, v) {
        let r, g, b;
        if (s === 0) {
            r = g = b = v;
            return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
        }

        h *= 6; // h is now 0-6
        const i = Math.floor(h);
        const f = h - i;
        const p = v * (1 - s);
        const q = v * (1 - s * f);
        const t = v * (1 - s * (1 - f));

        switch (i) {
            case 0: r = v; g = t; b = p; break;
            case 1: r = q; g = v; b = p; break;
            case 2: r = p; g = v; b = t; break;
            case 3: r = p; g = q; b = v; break;
            case 4: r = t; g = p; b = v; break;
            default: r = v; g = p; b = q; // case 5
        }

        return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
    }
}

window.SunburstColor = SunburstColor;


// Original license:

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
// The license and copyright below apply to the hsvToRgb function and colors.
//
//
//
// A javascript library for drawing &quot;sunburst&quot; species trees using 
// the HTML5 canvas.
//
// jt6 20100611 WTSI
//
// $Id$
//
// Copyright (c) 2010: Genome Research Ltd.
// 
// Authors: Rob Finn (rdf@sanger.ac.uk), John Tate (jt6@sanger.ac.uk)
// 
// This is free software; you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free Software
// Foundation; either version 2 of the License, or (at your option) any later
// version.
//
// This program is distributed in the hope that it will be useful, but WITHOUT
// ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
// FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
// details.
//
// You should have received a copy of the GNU General Public License along with
// this program. If not, see <http://www.gnu.org/licenses/>.
//----------------------------------------------------------------------------

