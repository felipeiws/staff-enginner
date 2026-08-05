import type { Meta, StoryObj } from '@storybook/react-vite';
import { MemoryRouter } from 'react-router-dom';
import { RouteErrorPage } from './route-error-page';
const meta: Meta<typeof RouteErrorPage> = {
  title: 'Estados/Erro de rota',
  component: RouteErrorPage,
  decorators: [
    (Story) => (
      <MemoryRouter>
        <Story />
      </MemoryRouter>
    ),
  ],
};
export default meta;
type Story = StoryObj<typeof meta>;
export const NaoEncontrada: Story = { args: { notFound: true } };
