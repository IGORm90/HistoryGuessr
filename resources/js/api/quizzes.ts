import { api } from './client';
import type { Quiz } from '@/types/quiz';

export const quizzesApi = {
    list: () => api.get<Quiz[]>('/quizzes'),
};
