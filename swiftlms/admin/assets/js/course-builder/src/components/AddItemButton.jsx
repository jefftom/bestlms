/**
 * Add Item Button Component
 */

import React from 'react';

const AddItemButton = ({ label, onClick, size = 'normal' }) => {
    return (
        <button
            type="button"
            className={`sfls-add-item-btn sfls-add-item-${size}`}
            onClick={onClick}
        >
            <span className="dashicons dashicons-plus-alt2"></span>
            {label}
        </button>
    );
};

export default AddItemButton;
