/**
 * Course Builder Component
 *
 * Main component for the drag-and-drop curriculum builder.
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    DragOverlay,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import SectionItem from './SectionItem';
import LessonItem from './LessonItem';
import AddItemButton from './AddItemButton';
import LessonEditor from './LessonEditor';
import PreviewPanel from './PreviewPanel';

const CourseBuilder = ({ courseId, nonce, restUrl }) => {
    const [sections, setSections] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeId, setActiveId] = useState(null);
    const [activeDragItem, setActiveDragItem] = useState(null);
    const [editingLesson, setEditingLesson] = useState(null);
    const [showPreview, setShowPreview] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    // Load curriculum data.
    useEffect(() => {
        loadCurriculum();
    }, [courseId]);

    const loadCurriculum = async () => {
        try {
            const response = await fetch(`${restUrl}courses/${courseId}/curriculum`, {
                headers: {
                    'X-WP-Nonce': nonce,
                },
            });
            const data = await response.json();
            setSections(data.sections || []);
        } catch (error) {
            console.error('Failed to load curriculum:', error);
        } finally {
            setLoading(false);
        }
    };

    const saveCurriculum = async () => {
        setSaving(true);
        try {
            await fetch(`${restUrl}courses/${courseId}/curriculum`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({ sections }),
            });
            setHasChanges(false);
        } catch (error) {
            console.error('Failed to save curriculum:', error);
            alert('Failed to save changes. Please try again.');
        } finally {
            setSaving(false);
        }
    };

    const handleDragStart = (event) => {
        const { active } = event;
        setActiveId(active.id);

        // Find the dragged item.
        const item = findItemById(active.id);
        setActiveDragItem(item);
    };

    const handleDragEnd = (event) => {
        const { active, over } = event;

        setActiveId(null);
        setActiveDragItem(null);

        if (!over || active.id === over.id) {
            return;
        }

        const activeItem = findItemById(active.id);
        const overItem = findItemById(over.id);

        if (!activeItem) return;

        // Handle section reordering.
        if (activeItem.type === 'section') {
            const oldIndex = sections.findIndex(s => s.id === active.id);
            const newIndex = sections.findIndex(s => s.id === over.id);

            if (oldIndex !== -1 && newIndex !== -1) {
                setSections(arrayMove(sections, oldIndex, newIndex));
                setHasChanges(true);
            }
        }
        // Handle lesson reordering within or between sections.
        else if (activeItem.type === 'lesson') {
            const newSections = [...sections];

            // Find source section and index.
            let sourceSection = null;
            let sourceIndex = -1;
            let targetSection = null;
            let targetIndex = -1;

            for (const section of newSections) {
                const lessonIndex = section.lessons.findIndex(l => l.id === active.id);
                if (lessonIndex !== -1) {
                    sourceSection = section;
                    sourceIndex = lessonIndex;
                }

                if (overItem.type === 'lesson') {
                    const targetLessonIndex = section.lessons.findIndex(l => l.id === over.id);
                    if (targetLessonIndex !== -1) {
                        targetSection = section;
                        targetIndex = targetLessonIndex;
                    }
                } else if (overItem.type === 'section' && section.id === over.id) {
                    targetSection = section;
                    targetIndex = section.lessons.length;
                }
            }

            if (sourceSection && targetSection) {
                // Remove from source.
                const [movedLesson] = sourceSection.lessons.splice(sourceIndex, 1);

                // Add to target.
                if (sourceSection.id === targetSection.id) {
                    // Same section - adjust index.
                    if (sourceIndex < targetIndex) {
                        targetIndex--;
                    }
                }
                targetSection.lessons.splice(targetIndex, 0, movedLesson);

                setSections(newSections);
                setHasChanges(true);
            }
        }
    };

    const findItemById = (id) => {
        // Check sections.
        const section = sections.find(s => s.id === id);
        if (section) return { ...section, type: 'section' };

        // Check lessons.
        for (const section of sections) {
            const lesson = section.lessons.find(l => l.id === id);
            if (lesson) return { ...lesson, type: 'lesson', sectionId: section.id };
        }

        return null;
    };

    const addSection = () => {
        const newSection = {
            id: `section-${Date.now()}`,
            title: 'New Section',
            lessons: [],
            isNew: true,
        };

        setSections([...sections, newSection]);
        setHasChanges(true);
    };

    const updateSection = (sectionId, updates) => {
        setSections(sections.map(section =>
            section.id === sectionId ? { ...section, ...updates } : section
        ));
        setHasChanges(true);
    };

    const deleteSection = (sectionId) => {
        if (!confirm('Delete this section and all its lessons?')) return;

        setSections(sections.filter(section => section.id !== sectionId));
        setHasChanges(true);
    };

    const addLesson = (sectionId) => {
        const newLesson = {
            id: `lesson-${Date.now()}`,
            title: 'New Lesson',
            type: 'video',
            duration: 0,
            isNew: true,
        };

        setSections(sections.map(section =>
            section.id === sectionId
                ? { ...section, lessons: [...section.lessons, newLesson] }
                : section
        ));
        setHasChanges(true);
        setEditingLesson({ ...newLesson, sectionId });
    };

    const updateLesson = (sectionId, lessonId, updates) => {
        setSections(sections.map(section =>
            section.id === sectionId
                ? {
                    ...section,
                    lessons: section.lessons.map(lesson =>
                        lesson.id === lessonId ? { ...lesson, ...updates } : lesson
                    ),
                }
                : section
        ));
        setHasChanges(true);
    };

    const deleteLesson = (sectionId, lessonId) => {
        if (!confirm('Delete this lesson?')) return;

        setSections(sections.map(section =>
            section.id === sectionId
                ? { ...section, lessons: section.lessons.filter(l => l.id !== lessonId) }
                : section
        ));
        setHasChanges(true);
    };

    const handleLessonSave = (lessonData) => {
        if (editingLesson) {
            updateLesson(editingLesson.sectionId, editingLesson.id, lessonData);
        }
        setEditingLesson(null);
    };

    if (loading) {
        return (
            <div className="sfls-builder-loading">
                <div className="sfls-spinner"></div>
                <p>Loading curriculum...</p>
            </div>
        );
    }

    return (
        <div className="sfls-course-builder">
            <div className="sfls-builder-header">
                <h2>Course Curriculum</h2>
                <div className="sfls-builder-actions">
                    <button
                        type="button"
                        className="button"
                        onClick={() => setShowPreview(!showPreview)}
                    >
                        {showPreview ? 'Hide Preview' : 'Preview'}
                    </button>
                    <button
                        type="button"
                        className="button button-primary"
                        onClick={saveCurriculum}
                        disabled={saving || !hasChanges}
                    >
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>
            </div>

            <div className="sfls-builder-content">
                <div className={`sfls-curriculum-panel ${showPreview ? 'with-preview' : ''}`}>
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragStart={handleDragStart}
                        onDragEnd={handleDragEnd}
                    >
                        <SortableContext
                            items={sections.map(s => s.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            <div className="sfls-sections-list">
                                {sections.map((section, index) => (
                                    <SectionItem
                                        key={section.id}
                                        section={section}
                                        index={index}
                                        onUpdate={(updates) => updateSection(section.id, updates)}
                                        onDelete={() => deleteSection(section.id)}
                                        onAddLesson={() => addLesson(section.id)}
                                        onEditLesson={(lesson) => setEditingLesson({ ...lesson, sectionId: section.id })}
                                        onDeleteLesson={(lessonId) => deleteLesson(section.id, lessonId)}
                                        onUpdateLesson={(lessonId, updates) => updateLesson(section.id, lessonId, updates)}
                                    />
                                ))}
                            </div>
                        </SortableContext>

                        <DragOverlay>
                            {activeDragItem && (
                                <div className="sfls-drag-overlay">
                                    {activeDragItem.type === 'section' ? (
                                        <div className="sfls-section-drag">
                                            {activeDragItem.title}
                                        </div>
                                    ) : (
                                        <div className="sfls-lesson-drag">
                                            {activeDragItem.title}
                                        </div>
                                    )}
                                </div>
                            )}
                        </DragOverlay>
                    </DndContext>

                    <AddItemButton
                        label="Add Section"
                        onClick={addSection}
                    />
                </div>

                {showPreview && (
                    <PreviewPanel sections={sections} />
                )}
            </div>

            {editingLesson && (
                <LessonEditor
                    lesson={editingLesson}
                    onSave={handleLessonSave}
                    onClose={() => setEditingLesson(null)}
                    restUrl={restUrl}
                    nonce={nonce}
                />
            )}
        </div>
    );
};

export default CourseBuilder;
