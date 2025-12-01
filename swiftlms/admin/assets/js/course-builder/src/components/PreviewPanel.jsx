/**
 * Preview Panel Component
 *
 * Shows how the curriculum will appear to students.
 */

import React, { useState } from 'react';

const LESSON_TYPE_ICONS = {
    video: 'dashicons-video-alt3',
    text: 'dashicons-media-document',
    quiz: 'dashicons-forms',
    assignment: 'dashicons-portfolio',
    download: 'dashicons-download',
};

const PreviewPanel = ({ sections }) => {
    const [expandedSections, setExpandedSections] = useState(
        sections.reduce((acc, section) => ({ ...acc, [section.id]: true }), {})
    );

    const toggleSection = (sectionId) => {
        setExpandedSections(prev => ({
            ...prev,
            [sectionId]: !prev[sectionId],
        }));
    };

    const totalLessons = sections.reduce((acc, section) => acc + section.lessons.length, 0);
    const totalDuration = sections.reduce(
        (acc, section) => acc + section.lessons.reduce((lessonAcc, lesson) => lessonAcc + (lesson.duration || 0), 0),
        0
    );

    const formatDuration = (minutes) => {
        if (!minutes) return '0m';
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours > 0) {
            return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
        }
        return `${mins}m`;
    };

    return (
        <div className="sfls-preview-panel">
            <div className="sfls-preview-header">
                <h3>Student Preview</h3>
                <div className="sfls-preview-stats">
                    <span>{sections.length} sections</span>
                    <span>{totalLessons} lessons</span>
                    <span>{formatDuration(totalDuration)} total</span>
                </div>
            </div>

            <div className="sfls-preview-content">
                {sections.length === 0 ? (
                    <div className="sfls-preview-empty">
                        <span className="dashicons dashicons-welcome-learn-more"></span>
                        <p>No sections added yet</p>
                    </div>
                ) : (
                    <div className="sfls-preview-curriculum">
                        {sections.map((section, sectionIndex) => (
                            <div key={section.id} className="sfls-preview-section">
                                <div
                                    className="sfls-preview-section-header"
                                    onClick={() => toggleSection(section.id)}
                                >
                                    <span className="sfls-preview-section-toggle">
                                        <span className={`dashicons ${expandedSections[section.id] ? 'dashicons-arrow-down' : 'dashicons-arrow-right'}`}></span>
                                    </span>
                                    <span className="sfls-preview-section-title">
                                        Section {sectionIndex + 1}: {section.title}
                                    </span>
                                    <span className="sfls-preview-section-meta">
                                        {section.lessons.length} lessons
                                    </span>
                                </div>

                                {expandedSections[section.id] && (
                                    <div className="sfls-preview-lessons">
                                        {section.lessons.map((lesson, lessonIndex) => (
                                            <div key={lesson.id} className="sfls-preview-lesson">
                                                <span className="sfls-preview-lesson-number">
                                                    {lessonIndex + 1}
                                                </span>
                                                <span className={`dashicons ${LESSON_TYPE_ICONS[lesson.type] || 'dashicons-media-default'}`}></span>
                                                <span className="sfls-preview-lesson-title">
                                                    {lesson.title}
                                                </span>
                                                <span className="sfls-preview-lesson-meta">
                                                    {lesson.duration > 0 && (
                                                        <span className="duration">{formatDuration(lesson.duration)}</span>
                                                    )}
                                                    {lesson.isFree && (
                                                        <span className="free-badge">Free</span>
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};

export default PreviewPanel;
