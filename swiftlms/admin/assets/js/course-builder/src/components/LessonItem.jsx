/**
 * Lesson Item Component
 *
 * Sortable lesson item within a section.
 */

import React from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

const LESSON_TYPE_ICONS = {
    video: 'dashicons-video-alt3',
    text: 'dashicons-media-document',
    quiz: 'dashicons-forms',
    assignment: 'dashicons-portfolio',
    download: 'dashicons-download',
};

const LESSON_TYPE_LABELS = {
    video: 'Video',
    text: 'Text',
    quiz: 'Quiz',
    assignment: 'Assignment',
    download: 'Download',
};

const LessonItem = ({ lesson, index, onEdit, onDelete, onUpdate }) => {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: lesson.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    const formatDuration = (minutes) => {
        if (!minutes) return '';
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`sfls-lesson-item ${isDragging ? 'is-dragging' : ''}`}
        >
            <div className="sfls-lesson-drag-handle" {...attributes} {...listeners}>
                <span className="dashicons dashicons-menu"></span>
            </div>

            <div className="sfls-lesson-number">{index + 1}</div>

            <div className="sfls-lesson-type-icon">
                <span className={`dashicons ${LESSON_TYPE_ICONS[lesson.type] || 'dashicons-media-default'}`}></span>
            </div>

            <div className="sfls-lesson-info">
                <span className="sfls-lesson-title">{lesson.title}</span>
                <span className="sfls-lesson-type-label">{LESSON_TYPE_LABELS[lesson.type] || lesson.type}</span>
            </div>

            <div className="sfls-lesson-meta">
                {lesson.duration > 0 && (
                    <span className="sfls-lesson-duration">
                        <span className="dashicons dashicons-clock"></span>
                        {formatDuration(lesson.duration)}
                    </span>
                )}
                {lesson.isFree && (
                    <span className="sfls-lesson-free">Free</span>
                )}
                {lesson.isPreview && (
                    <span className="sfls-lesson-preview">Preview</span>
                )}
            </div>

            <div className="sfls-lesson-actions">
                <button
                    type="button"
                    className="sfls-action-btn sfls-edit-btn"
                    onClick={onEdit}
                    title="Edit Lesson"
                >
                    <span className="dashicons dashicons-edit"></span>
                </button>
                <button
                    type="button"
                    className="sfls-action-btn sfls-delete-btn"
                    onClick={onDelete}
                    title="Delete Lesson"
                >
                    <span className="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
    );
};

export default LessonItem;
