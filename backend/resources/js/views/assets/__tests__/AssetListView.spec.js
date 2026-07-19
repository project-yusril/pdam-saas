import { flushPromises, shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import AssetListView from '../AssetListView.vue'

const { get } = vi.hoisted(() => ({
  get: vi.fn().mockResolvedValue({ data: { data: [] } }),
}))

vi.mock('../../../api', () => ({
  default: { get },
}))

describe('AssetListView', () => {
  it('loads assets from the backend asset route', async () => {
    shallowMount(AssetListView)
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/assets')
  })
})
