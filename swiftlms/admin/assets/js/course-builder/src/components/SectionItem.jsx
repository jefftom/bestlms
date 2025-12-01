/**
 * Section Item Component
 *
 * Sortable section with nested lessons.
 */

import React, { useState } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    SortableContext,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import LessonItem from './LessonItem';
import AddItemButton from './AddItemButton';

const SectionItem = ({
    section,
    index,
    onUpdate,
    onDelete,
    onAddLesson,
    onEditLesson,
    onDeleteLesson,
    onUpdateLesson,
}) => {
    const [isEditing, setIsEditing] = useState(section.isNew || false);
    const [title, setTitle] = useState(section.title);
    const [isExpanded, setIsExpanded] = useState(true);

    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: section.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    const handleTitleSave = () => {
        onUpdate({ title, isNew: false });
        setIsEditing(false);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            handleTitleSave();
        } else if (e.key === 'Escape') {
            setTitle(section.title);
            setIsEditing(false);
        }
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`sfls-section-item ${isDragging ? 'is-dragging' : ''}`}
        >
            <div className="sfls-section-header">
                <div className="sfls-section-drag-handle" {...attributes} {...listeners}>
                    <span className="dashicons dashicons-menu"></span>
                </div>

                <div className="sfls-section-number">{index + 1}</div>

                <div className="sfls-section-title">
                    {isEditing ? (
                        <input
                            type="text"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            onBlur={handleTitleSave}
                            onKeyDown={handleKeyDown}
                            autoFocus
                            className="sfls-section-title-input"
                        />
                    ) : (
                        <span
                            className="sfls-section-title-text"
                            onClick={() => setIsEditing(true)}
                        >
                            {section.title}
                        </span>
                    )}
                </div>

                <div className="sfls-section-meta">
                    <span className="sfls-lesson-count">
                        {section.lessons.length} {section.lessons.length === 1 ? 'lesson' : 'lessons'}
                    </span>
                </div>

                <div className="sfls-section-actions">
                    <button
                        type="button"
                        className="sfls-action-btn"
                        onClick={() => setIsExpanded(!isExpanded)}
                        title={isExpanded ? 'Collapse' : 'Expand'}
                    >
                        <span className={`dashicons ${isExpanded ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`}></span>
                    </button>
                    <button
                        type="button"
                        className="sfls-action-btn sfls-edit-btn"
                        onClick={() => setIsEditing(true)}
                        title="Edit Section"
                    >
                        <span className="dashicons dashicons-edit"></span>
                    </button>
                    <button
                        type="button"
                        className="sfls-action-btn sfls-delete-btn"
                        onClick={onDelete}
                        title="Delete Section"
                    >
                        <span className="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>

            {isExpanded && (
                <div className="sfls-section-content">
                    <SortableContext
                        items={section.lessons.map(l => l.id)}
                        strategy={verticalListSortingStrategy}
                    >
                        <div className="sfls-lessons-list">
                            {section.lessons.map((lesson, lessonIndex) => (
                                <LessonItem
                                    key={lesson.id}
                                    lesson={lesson}
                                    index={lessonIndex}
                                    onEdit={() => onEditLesson(lesson)}
                                    onDelete={() => onDeleteLesson(lesson.id)}
                                    onUpdate={(updates) => onUpdateLesson(lesson.id, updates)}
                                />
                            ))}
                        </div>
                    </SortableContext>

                    <AddItemButton
                        label="Add Lesson"
                        onClick={onAddLesson}
                        size="small"
                    />
                </div>
            )}
        </div>
    );
};

export default SectionItem;
