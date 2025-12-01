/**
 * SwiftLMS Course Builder
 *
 * React-based drag-and-drop course curriculum builder.
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import CourseBuilder from './components/CourseBuilder';
import './styles/main.scss';

// Initialize when DOM is ready.
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('sfls-course-builder-root');

    if (container) {
        const root = createRoot(container);
        const courseId = container.dataset.courseId;
        const nonce = container.dataset.nonce;
        const restUrl = container.dataset.restUrl;

        root.render(
            <CourseBuilder
                courseId={courseId}
                nonce={nonce}
                restUrl={restUrl}
            />
        );
    }
});
